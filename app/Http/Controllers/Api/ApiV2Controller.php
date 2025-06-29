<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAccessedApi;
use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use App\Transformers\ApiTransformer;
use App\Transformers\CategoryTransformer;
use App\Transformers\DetailsTransformer;
use Blacklight\Releases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApiV2Controller extends BasePageController
{
    private ApiController $api;

    public function __construct()
    {
        $this->api = new ApiController;
    }

    public function capabilities(): JsonResponse
    {
        $category = Category::getForApi();

        $capabilities = [
            'server' => [
                'title' => config('app.name'),
                'strapline' => Settings::settingValue('strapline'),
                'email' => config('mail.from.address'),
                'url' => url('/'),
            ],
            'limits' => [
                'max' => 100,
                'default' => 100,
            ],
            'registration' => [
                'available' => 'no',
                'open' => (int) Settings::settingValue('registerstatus') === 0 ? 'yes' : 'no',
            ],
            'searching' => [
                'search' => ['available' => 'yes', 'supportedParams' => 'id'],
                'tv-search' => ['available' => 'yes', 'supportedParams' => 'id,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep'],
                'movie-search' => ['available' => 'yes', 'supportedParams' => 'id, imdbid, tmdbid, traktid'],
                'audio-search' => ['available' => 'no',  'supportedParams' => ''],
            ],
            'categories' => fractal($category, new CategoryTransformer),
        ];

        return response()->json($capabilities);
    }

    /**
     * @throws \Throwable
     */
    public function movie(Request $request): JsonResponse
    {
        // Validate API token and get user in one query
        if ($request->missing('api_token') || $request->isNotFilled('api_token')) {
            return response()->json(['error' => 'Missing parameter (apikey)'], 403);
        }

        $user = User::query()
            ->where('api_token', $request->input('api_token'))
            ->with('role') // Eager load role to avoid N+1
            ->first();

        if (! $user) {
            return response()->json(['error' => 'Invalid API key'], 403);
        }

        // Process request asynchronously where possible
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        event(new UserAccessedApi($user));

        // Get request parameters efficiently
        $params = [
            'imdbId' => $request->input('imdbid', -1),
            'tmdbId' => $request->input('tmdbid', -1),
            'traktId' => $request->input('traktid', -1),
            'minSize' => max(0, (int) $request->input('minsize', 0)),
            'searchName' => $request->input('id', ''),
        ];

        // Perform search
        $relData = (new Releases)->moviesSearch(
            $params['imdbId'],
            $params['tmdbId'],
            $params['traktId'],
            $this->api->offset($request),
            $this->api->limit($request),
            $params['searchName'],
            $this->api->categoryID($request),
            $this->api->maxAge($request),
            $params['minSize'],
            User::getCategoryExclusionForApi($request)
        );

        // Get both timestamps in a single query
        $timestamps = DB::selectOne('
            SELECT
                (SELECT MIN(timestamp) FROM user_requests WHERE users_id = ?) as api_time,
                (SELECT MIN(timestamp) FROM user_downloads WHERE users_id = ?) as grab_time
        ', [$user->id, $user->id]);

        // Build response
        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'apiCurrent' => UserRequest::getApiRequests($user->id),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => UserDownload::getDownloadRequests($user->id),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $timestamps->api_time ? Carbon::parse($timestamps->api_time)->toRfc2822String() : '',
            'grabOldestTime' => $timestamps->grab_time ? Carbon::parse($timestamps->grab_time)->toRfc2822String() : '',
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function apiSearch(Request $request): JsonResponse
    {
        if ($request->missing('api_token') || $request->isNotFilled('api_token')) {
            return response()->json(['error' => 'Missing parameter (api_token)'], 403);
        }
        $releases = new Releases;
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        $offset = $this->api->offset($request);
        $catExclusions = User::getCategoryExclusionForApi($request);
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $maxAge = $this->api->maxAge($request);
        $groupName = $this->api->group($request);
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        event(new UserAccessedApi($user));
        $categoryID = $this->api->categoryID($request);
        $limit = $this->api->limit($request);

        if ($request->has('id')) {
            $relData = $releases->apiSearch(
                $request->input('id'),
                $groupName,
                $offset,
                $limit,
                $maxAge,
                $catExclusions,
                $categoryID,
                $minSize
            );
        } else {
            $relData = $releases->getBrowseRange(
                1,
                $categoryID,
                $offset,
                $limit,
                '',
                $maxAge,
                $catExclusions,
                $groupName,
                $minSize
            );
        }

        $time = UserRequest::whereUsersId($user->id)->min('timestamp');
        $apiOldestTime = $time !== null ? Carbon::createFromTimeString($time)->toRfc2822String() : '';
        $grabTime = UserDownload::whereUsersId($user->id)->min('timestamp');
        $oldestGrabTime = $grabTime !== null ? Carbon::createFromTimeString($grabTime)->toRfc2822String() : '';

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'apiCurrent' => UserRequest::getApiRequests($user->id),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => UserDownload::getDownloadRequests($user->id),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $apiOldestTime,
            'grabOldestTime' => $oldestGrabTime,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function tv(Request $request): JsonResponse
    {
        if ($request->missing('api_token') || $request->isNotFilled('api_token')) {
            return response()->json(['error' => 'Missing parameter (api_token)'], 403);
        }
        $releases = new Releases;
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        if ($user === null) {
            return response()->json(['error' => 'Invalid API Token'], 403);
        }
        $catExclusions = User::getCategoryExclusionForApi($request);
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $this->api->verifyEmptyParameter($request, 'id');
        $this->api->verifyEmptyParameter($request, 'vid');
        $this->api->verifyEmptyParameter($request, 'tvdbid');
        $this->api->verifyEmptyParameter($request, 'traktid');
        $this->api->verifyEmptyParameter($request, 'rid');
        $this->api->verifyEmptyParameter($request, 'tvmazeid');
        $this->api->verifyEmptyParameter($request, 'imdbid');
        $this->api->verifyEmptyParameter($request, 'tmdbid');
        $this->api->verifyEmptyParameter($request, 'season');
        $this->api->verifyEmptyParameter($request, 'ep');
        $maxAge = $this->api->maxAge($request);
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        event(new UserAccessedApi($user));

        $siteIdArr = [
            'id' => $request->input('vid') ?? null,
            'tvdb' => $request->input('tvdbid') ?? null,
            'trakt' => $request->input('traktid') ?? null,
            'tvrage' => $request->input('rid') ?? null,
            'tvmaze' => $request->input('tvmazeid') ?? null,
            'imdb' => $request->input('imdbid') ?? null,
            'tmdb' => $request->input('tmdbid') ?? null,
        ];

        // Process season only queries or Season and Episode/Airdate queries

        $series = $request->input('season') ?? '';
        $episode = $request->input('ep') ?? '';

        if (preg_match('#^(19|20)\d{2}$#', $series, $year) && str_contains($episode, '/')) {
            $airDate = str_replace('/', '-', $year[0].'-'.$episode);
        }

        $relData = $releases->apiTvSearch(
            $siteIdArr,
            $series,
            $episode,
            $airDate ?? '',
            $this->api->offset($request),
            $this->api->limit($request),
            $request->input('id') ?? '',
            $this->api->categoryID($request),
            $maxAge,
            $minSize,
            $catExclusions
        );

        $time = UserRequest::whereUsersId($user->id)->min('timestamp');
        $apiOldestTime = $time !== null ? Carbon::createFromTimeString($time)->toRfc2822String() : '';
        $grabTime = UserDownload::whereUsersId($user->id)->min('timestamp');
        $oldestGrabTime = $grabTime !== null ? Carbon::createFromTimeString($grabTime)->toRfc2822String() : '';

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'apiCurrent' => UserRequest::getApiRequests($user->id),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => UserDownload::getDownloadRequests($user->id),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $apiOldestTime,
            'grabOldestTime' => $oldestGrabTime,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    public function getNzb(Request $request): \Illuminate\Foundation\Application|JsonResponse|\Illuminate\Routing\Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if ($request->missing('api_token') || $request->isNotFilled('api_token')) {
            return response()->json(['error' => 'Missing parameter (api_token)'], 403);
        }
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        if ($user === null) {
            return response()->json(['error' => 'Invalid API Token'], 403);
        }
        event(new UserAccessedApi($user));
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        $relData = Release::checkGuidForApi($request->input('id'));
        if ($relData) {
            return redirect('/getnzb?r='.$request->input('api_token').'&id='.$request->input('id').(($request->has('del') && $request->input('del') === '1') ? '&del=1' : ''));
        }

        return response()->json(['data' => 'No such item (the guid you provided has no release in our database)'], 404);
    }

    public function details(Request $request): JsonResponse
    {
        if ($request->missing('api_token') || $request->isNotFilled('api_token')) {
            return response()->json(['error' => 'Missing parameter (api_token)'], 403);
        }
        if ($request->missing('id')) {
            return response()->json(['error' => 'Missing parameter (guid is required for single release details)'], 400);
        }

        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        if ($user === null) {
            return response()->json(['error' => 'Invalid API Token'], 403);
        }
        event(new UserAccessedApi($user));
        $relData = Release::getByGuid($request->input('id'));

        $relData = fractal($relData, new DetailsTransformer($user));

        return response()->json($relData);
    }
}
