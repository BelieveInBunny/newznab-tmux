<?php

namespace App\Http\Controllers;

use App\Models\DnzbFailure;
use App\Models\Predb;
use App\Models\Release;
use App\Models\ReleaseComment;
use App\Models\ReleaseFile;
use App\Models\ReleaseNfo;
use App\Models\ReleaseRegex;
use App\Models\Settings;
use App\Models\UserDownload;
use App\Models\Video;
use Blacklight\AniDB;
use Blacklight\Books;
use Blacklight\Console;
use Blacklight\Games;
use Blacklight\Movie;
use Blacklight\Music;
use Blacklight\ReleaseExtra;
use Blacklight\Releases;
use Blacklight\XXX;
use Illuminate\Http\Request;

class DetailsController extends BasePageController
{
    public function show(Request $request, string $guid)
    {
        if ($guid !== null) {
            $releases = new Releases;
            $re = new ReleaseExtra;
            $data = Release::getByGuid($guid);
            $releaseRegex = '';
            if (! empty($data)) {
                $releaseRegex = ReleaseRegex::query()->where('releases_id', '=', $data['id'])->first();
            }

            if (! $data) {
                return redirect()->back();
            }

            if ($this->isPostBack($request)) {
                ReleaseComment::addComment($data['id'], $data['gid'], $request->input('txtAddComment'), $this->userdata->id, $request->ip());
            }

            $nfoData = ReleaseNfo::getReleaseNfo($data['id']);
            $nfo = $nfoData ? $nfoData->nfo : null;
            $reVideo = $re->getVideo($data['id']);
            $reAudio = $re->getAudio($data['id']);
            $reSubs = $re->getSubs($data['id']);
            $comments = ReleaseComment::getComments($data['id']);
            $similars = $releases->searchSimilar($data['id'], $data['searchname'], $this->userdata->categoryexclusions);
            $failed = DnzbFailure::getFailedCount($data['id']);
            $downloadedBy = UserDownload::query()->with('user')->where('releases_id', $data['id'])->get(['users_id']);

            $showInfo = '';
            if ($data['videos_id'] > 0) {
                $showInfo = Video::getByVideoID($data['videos_id']);
            }

            $mov = '';
            if ($data['imdbid'] !== '' && $data['imdbid'] !== 0000000) {
                $movie = new Movie(['Settings' => $this->settings]);
                $mov = $movie->getMovieInfo($data['imdbid']);
                if (! empty($mov['title'])) {
                    $mov['title'] = str_replace(['/', '\\'], '', $mov['title']);
                    $mov['actors'] = makeFieldLinks($mov, 'actors', 'movies');
                    $mov['genre'] = makeFieldLinks($mov, 'genre', 'movies');
                    $mov['director'] = makeFieldLinks($mov, 'director', 'movies');
                    if (Settings::settingValue('trailers_display')) {
                        $trailer = empty($mov['trailer']) || $mov['trailer'] === '' ? $movie->getTrailer($data['imdbid']) : $mov['trailer'];
                        if ($trailer) {
                            $mov['trailer'] = sprintf('<iframe width="%d" height="%d" src="%s"></iframe>', Settings::settingValue('trailers_size_x'), Settings::settingValue('trailers_size_y'), $trailer);
                        }
                    }
                }
            }

            $xxx = '';
            if ($data['xxxinfo_id'] !== '' && $data['xxxinfo_id'] !== 0) {
                $x = new XXX;
                $xxx = $x->getXXXInfo($data['xxxinfo_id']);

                if (isset($xxx['trailers'])) {
                    $xxx['trailers'] = $x->insertSwf($xxx['classused'], $xxx['trailers']);
                }

                if ($xxx && isset($xxx['title'])) {
                    $xxx['title'] = str_replace(['/', '\\'], '', $xxx['title']);
                    $xxx['actors'] = makeFieldLinks($xxx, 'actors', 'xxx');
                    $xxx['genre'] = makeFieldLinks($xxx, 'genre', 'xxx');
                    $xxx['director'] = makeFieldLinks($xxx, 'director', 'xxx');
                } else {
                    $xxx = false;
                }
            }

            $game = '';
            if ($data['gamesinfo_id'] !== '') {
                $game = (new Games)->getGamesInfoById($data['gamesinfo_id']);
            }

            $mus = '';
            if ($data['musicinfo_id'] !== '') {
                $mus = (new Music)->getMusicInfo($data['musicinfo_id']);
            }

            $book = '';
            if ($data['bookinfo_id'] !== '') {
                $book = (new Books)->getBookInfo($data['bookinfo_id']);
            }

            $con = '';
            if ($data['consoleinfo_id'] !== '') {
                $con = (new Console)->getConsoleInfo($data['consoleinfo_id']);
            }

            $AniDBAPIArray = '';
            if ($data['anidbid'] > 0) {
                $AniDBAPIArray = (new AniDB)->getAnimeInfo($data['anidbid']);
            }

            $pre = Predb::getForRelease($data['predb_id']);

            $releasefiles = ReleaseFile::getReleaseFiles($data['id']);

            $this->viewData = array_merge($this->viewData, [
                'releasefiles' => $releasefiles,
                'release' => $data,
                'reVideo' => $reVideo,
                'reAudio' => $reAudio,
                'reSubs' => $reSubs,
                'nfo' => $nfo,
                'show' => $showInfo,
                'movie' => $mov,
                'xxx' => $xxx,
                'anidb' => $AniDBAPIArray,
                'music' => $mus,
                'con' => $con,
                'game' => $game,
                'book' => $book,
                'predb' => $pre,
                'comments' => $comments,
                'files' => $releasefiles,
                'searchname' => getSimilarName($data['searchname']),
                'similars' => $similars !== false ? $similars : [],
                'privateprofiles' => config('nntmux_settings.private_profiles'),
                'failed' => $failed,
                'regex' => $releaseRegex,
                'downloadedby' => $downloadedBy,
                'meta_title' => 'View NZB',
                'meta_keywords' => 'view,nzb,description,details',
                'meta_description' => 'View NZB for '.$data['searchname'],
            ]);

            return view('details.index', $this->viewData);
        }

        return redirect()->back()->with('error', 'Release not found');
    }
}
