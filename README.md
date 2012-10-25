ee_tube
===============
ExpressionEngine plugin for grabbing video id, embed code, title, content, author, duration, comment count, categories, tags, & thumbnails for a YouTube url

Parameters
===============
url (required)				 - (string)		 - The YouTube url you want information for.
width (optional)			 - (int)		 - The width of the YouTube embed code.
autoplay (optional)			 - (boolean)	 - Should the video autoplay.

Tags
===============
{eet_id}					 - (string)		 - The video on YouTube.
{eet_embed}					 - (xhtml)		 - The embed code for Youtube video.
{eet_title}					 - (string)		 - The title of the video.
{eet_content}				 - (xhtml)		 - The discription from Youtube.
{eet_author}				 - (string)		 - YouTube video author or channel.
{eet_duration}				 - (string)		 - Length of video formatted hh:mm:ss.
{eet_duration_seconds}		 - (int)		 - Length of video in seconds.
{eet_comment_count}			 - (int)		 - Numbr of comments on YouTube.
{eet_categories}			 - (tag pair)	 - Tag pair for categories.
	{eet_category}			 - (string)		 - Single category name available inside tag pair.
{eet_tags}					 - (tag pair)	 - Tags as a tag piar.
	{eet_tag}				 - (string)		 - Single tag inside tag pair.
{eet_thumbnails}			 - (tag pair)	 - Tag pair of thumbnails.
  	{eet_thumbnail_src}		 - (string)		 - URL of the thumbnail.
	{eet_thumbnail_height}	 - (int)		 - Height of the thumbnail.
	{eet_thumbnail_width}	 - (int)		 - Width of thumbnail.
	{eet_thumbnail_time}	 - (string)		 - Time in the video where the thumbnail is taken.

Examples
===============
	{exp:ee_tube
		url="http://www.youtube.com/embed/GokKUqLcvD8"
		width="600"
		autoplay="TRUE"
	}
		
		{if no_results}

			<p class="sorry">Sorry, we couldn't find what you where looking for.</p>

		{/if}

		{if eet_duration_seconds > 10}

			<div id="video-player">

				{eet_embed}
				
			</div><!-- /#video-player -->

			<h1 id="video-title">{eet_title} <small>({eet_duration})</small></h1>

			<p class="author">{eet_author}</p>

			<p>{eet_content}</p>

			<a href="http://youtu.be/{eet_id}" target="_blank">{eet_comment_count} Total Comments on YouTube.</a>

			{eet_thumbnails}

				{if eet_thumbnail_width > 120}
				
					<img src="{eet_thumbnail_src}" alt="{eet_title} - {eet_thumbnail_time}" width="{eet_thumbnail_width}" height="{eet_thumbnail_height}" />

				{/if}

			{/eet_thumbnails}

			{eet_categories}

				{if count == 1}

					<h3>Categories</h3>

					<ul>

				{/if}

					<li>{eet_category}</li>

				{if count == total_results}

					</ul>

				{/if}

			{/eet_categories}

			{eet_tags}

				{if count == 1}

					<h3>Tags</h3>

					<ul>

				{/if}

					<li>{eet_tag}</li>

				{if count == total_results}

					</ul>

				{/if}

			{/eet_tags}

		{/if}

	{/exp:ee_tube}