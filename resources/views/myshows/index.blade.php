<style>
    .header-gradient {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    }
    .info-card-gradient {
        background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%);
    }
    .table-header-gradient {
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    }
    .show-avatar {
        background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
    }
    .category-badge {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    }
    .empty-state-bg {
        background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
    }
    table {
        border-collapse: collapse;
    }
    table thead tr {
        border-bottom: 2px solid #e5e7eb;
    }
    table tbody tr {
        border-bottom: 1px solid #e5e7eb;
    }
    table tbody tr:last-child {
        border-bottom: none;
    }
</style>

<div class="max-w-7xl mx-auto">
    <!-- Header Section -->
    <div class="header-gradient rounded-xl shadow-lg mb-6 overflow-hidden">
        <div class="px-8 py-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-white flex items-center">
                        <i class="fa fa-tv mr-3"></i>My TV Shows
                    </h1>
                    <p class="text-blue-100 mt-2">Manage your favorite TV series and get automatic updates</p>
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm text-blue-100">
                        <li><a href="{{ url($site->home_link) }}" class="hover:text-white transition">Home</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-white font-medium">My TV Shows</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- RSS Feed Info Card -->
    <div class="info-card-gradient border-l-4 border-blue-500 rounded-lg p-5 mb-6 shadow">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-500 text-white">
                    <i class="fa fa-rss text-lg"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Automatic Downloads via RSS</h3>
                <p class="text-sm text-gray-700">
                    Bookmark your favorite series below. New episodes are automatically added to your
                    <a href="{{ url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
                       class="font-semibold text-blue-600 hover:text-blue-800 underline inline-flex items-center">
                        <i class="fa fa-rss mr-1"></i>personal RSS feed
                    </a>
                    for automatic downloading.
                </p>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 mb-6">
        <a class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
           href="{{ url('/browse/TV') }}"
           title="View available TV series">
            <i class="fa fa-list mr-2"></i>Browse All Series
        </a>
        <a class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
           href="{{ url('/myshows/browse') }}"
           title="View a list of all releases in your shows">
            <i class="fa fa-search mr-2"></i>View Releases
        </a>
        <a class="px-6 py-3 bg-white text-gray-700 border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
           href="{{ url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
           title="All releases in your shows as an RSS feed">
            <i class="fa fa-rss mr-2 text-orange-500"></i>RSS Feed
        </a>
    </div>

    <!-- Shows Table/Cards -->
    @if(count($shows ?? []) > 0)
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
            <!-- Table Header -->
            <div class="table-header-gradient px-6 py-4 border-b-2 border-gray-300">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fa fa-bookmark mr-2 text-blue-600"></i>
                        Your Shows <span class="ml-2 px-3 py-1 bg-blue-600 text-white text-sm rounded-full font-bold">{{ count($shows) }}</span>
                    </h2>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th scope="col" class="px-8 py-5 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Series Name
                            </th>
                            <th scope="col" class="px-8 py-5 text-left text-xs font-bold text-gray-700 uppercase tracking-wider" style="width:180px">
                                Categories
                            </th>
                            <th scope="col" class="px-8 py-5 text-left text-xs font-bold text-gray-700 uppercase tracking-wider" style="width:160px">
                                Added Date
                            </th>
                            <th scope="col" class="px-8 py-5 text-center text-xs font-bold text-gray-700 uppercase tracking-wider" style="width:170px">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @foreach($shows as $show)
                            <tr class="hover:bg-blue-50 transition-colors duration-150">
                                <td class="px-8 py-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12 show-avatar rounded-lg flex items-center justify-center text-white font-bold text-lg shadow-sm">
                                            {{ strtoupper(substr($show['title'] ?? 'T', 0, 1)) }}
                                        </div>
                                        <div class="ml-4">
                                            <a class="text-base font-semibold text-gray-900 hover:text-blue-600 transition-colors"
                                               title="View series details"
                                               href="{{ url("/series/{$show['videos_id']}") }}">
                                                {{ $show['title'] ?? '' }}
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5">
                                    @if(!empty($show['categoryNames']))
                                        <span class="inline-flex items-center px-3 py-1.5 category-badge text-blue-800 text-xs font-semibold rounded-full border border-blue-200">
                                            <i class="fa fa-folder-open mr-1.5"></i>{{ e($show['categoryNames']) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-300">
                                            <i class="fa fa-folder mr-1.5"></i>All Categories
                                        </span>
                                    @endif
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ isset($show['created_at']) ? date('M d, Y', strtotime($show['created_at'])) : '' }}
                                        </span>
                                        <span class="text-xs text-gray-500 flex items-center mt-1">
                                            <i class="fa fa-clock mr-1"></i>
                                            {{ isset($show['created_at']) ? \Carbon\Carbon::parse($show['created_at'])->diffForHumans() : '' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center justify-center gap-2">
                                        <a class="inline-flex items-center px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 shadow hover:shadow-md transition-all duration-200 text-sm font-medium"
                                           href="{{ url("/myshows?action=edit&id={$show['videos_id']}") }}"
                                           title="Edit Categories">
                                            <i class="fa fa-edit mr-1.5"></i>
                                            <span class="hidden xl:inline">Edit</span>
                                        </a>
                                        <a class="inline-flex items-center px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 shadow hover:shadow-md transition-all duration-200 text-sm font-medium confirm_action"
                                           href="{{ url("/myshows?action=delete&id={$show['videos_id']}") }}"
                                           title="Remove from My Shows">
                                            <i class="fa fa-trash mr-1.5"></i>
                                            <span class="hidden xl:inline">Delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="md:hidden">
                @foreach($shows as $show)
                    <div class="p-6 border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center flex-1">
                                <div class="flex-shrink-0 h-12 w-12 show-avatar rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-sm">
                                    {{ strtoupper(substr($show['title'] ?? 'T', 0, 1)) }}
                                </div>
                                <div class="ml-3 flex-1">
                                    <a class="text-base font-semibold text-gray-900 hover:text-blue-600 block"
                                       href="{{ url("/series/{$show['videos_id']}") }}">
                                        {{ $show['title'] ?? '' }}
                                    </a>
                                    <div class="mt-1.5">
                                        @if(!empty($show['categoryNames']))
                                            <span class="inline-flex items-center px-2 py-1 category-badge text-blue-800 text-xs font-semibold rounded-full border border-blue-200">
                                                <i class="fa fa-folder-open mr-1"></i>{{ e($show['categoryNames']) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-300">
                                                All Categories
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200">
                            <div class="text-xs text-gray-500 flex items-center">
                                <i class="fa fa-calendar mr-1"></i>
                                {{ isset($show['created_at']) ? date('M d, Y', strtotime($show['created_at'])) : '' }}
                            </div>
                            <div class="flex gap-2">
                                <a class="px-3 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 text-sm font-medium shadow"
                                   href="{{ url("/myshows?action=edit&id={$show['videos_id']}") }}"
                                   title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <a class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm font-medium shadow confirm_action"
                                   href="{{ url("/myshows?action=delete&id={$show['videos_id']}") }}"
                                   title="Remove">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
            <div class="text-center py-16 px-6">
                <div class="mx-auto h-24 w-24 empty-state-bg rounded-full flex items-center justify-center mb-6 shadow-sm">
                    <i class="fa fa-tv text-5xl text-blue-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">No TV Shows Yet</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    You haven't bookmarked any TV series yet. Start building your collection by browsing our series library.
                </p>
                <a href="{{ url('/browse/TV') }}"
                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md hover:shadow-lg transition-all duration-200 font-medium">
                    <i class="fa fa-search mr-2"></i>Browse Series Library
                </a>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirm before removing shows
    document.querySelectorAll('.confirm_action').forEach(element => {
        element.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this show from your list?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

