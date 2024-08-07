<div class="card card-body">
	<h1>{$title}</h1>
	<table class="data table table-striped responsive-utilities jambo-table Sortable">
		<tr>
			<th style="width:60px;">ordinal</th>
			<th style="width:30px;">id</th>
			<th>title</th>
			<th>url</th>
			<th>type</th>
			<th>status</th>
			<th>role</th>
			<th>in menu</th>
			<th>body</th>
			<th>options</th>
		</tr>
		{foreach from=$contentlist item=content}
			<tr class="{cycle values=",alt"}">
				<td>{$content->ordinal}</td>
				<td>{$content->id}</td>
				<td><a href="{{url("/admin/content-add?id={$content->id}")}}"
					   title="Edit {$content->title}">{$content->title}</a></td>
				<td><a title="Preview in new window" href="{{url("/{$content->url}c{$content->id}")}}"
					   target="null">{$content->url}c{$content->id}</a></td>
				<td>
					{if $content->contenttype == "1"}
						Useful Link
					{elseif $content->contenttype == "2"}
						Article
					{elseif $content->contenttype == "3"}
						Homepage
					{/if}
				</td>
				<td>
					{if $content->status == "1"}
						Enabled
					{else}
						Disabled
					{/if}
				</td>
				<td>
					{if $content->role == "0"}
						Everyone
					{elseif $content->role == "1"}
						Users
					{elseif $content->role == "2"}
						Admins
					{/if}
				</td>
				<td title="{$content->body|escape:'htmlall'}">{$content->body|truncate:100|escape:'htmlall'}</td>
				<td><a class="confirm_action"
					   href="{{url("/admin/content-delete?id={$content->id}")}}">delete</a></td>
			</tr>
		{/foreach}
	</table>
</div>
