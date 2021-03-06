<?php

function postfilter($msg) {
	$markdown = new Parsedown();
	$markdown->setSafeMode(true);
	$msg = $markdown->text($msg);

	$msg = preg_replace("'\[reply=\"(.*?)\" id=\"(.*?)\"\]'si", '<blockquote><span class="quotedby"><small><i><a href=showprivate.php?id=\\2>Sent by \\1</a></i></small></span><hr>', $msg);
	$msg = str_replace('[/reply]', '<hr></blockquote>', $msg);
	$msg = preg_replace("'\[quote=\"(.*?)\" id=\"(.*?)\"\]'si", '<blockquote><span class="quotedby"><small><i><a href=thread.php?pid=\\2#\\2>Posted by \\1</a></i></small></span><hr>', $msg);
	$msg = str_replace('[/quote]', '<hr></blockquote>', $msg);

	return $msg;
}

function esc($text) {
	$text = str_replace('&', '&amp;', $text);
	$text = str_replace('<', '&lt;', $text);
	$text = str_replace('"', '&quot;', $text);
	$text = str_replace('>', '&gt;', $text);
	return $text;
}

function threadpost($post, $pthread = '') {
	global $dateformat, $userdata;

	$post['ranktext'] = getrank(0, $post['uposts']);
	$post['utitle'] = $post['ranktext']
			. ((strlen($post['ranktext']) >= 1) ? '<br>' : '')
			. $post['utitle']
			. ((strlen($post['utitle']) >= 1) ? '<br>' : '');

	if (isset($post['deleted']) && $post['deleted']) {
		$postlinks = '';
		if (canCreateForumPosts(getForumByThread($post['thread']))) {
			$postlinks .= "<a href=\"thread.php?pid=$post[id]&pin=$post[id]&rev=$post[revision]#$post[id]\">Peek</a> &bull; ";
			$postlinks .= "<a href=\"editpost.php?pid=" . $post['id'] . "&act=undelete\">Undelete</a>";
		}

		if ($post['id'])
			$postlinks .= ($postlinks ? ' &bull; ' : '') . "ID: $post[id]";

		$ulink = userlink($post, 'u');
		$text = <<<HTML
<table class="c1"><tr>
	<td class="b n1" style="border-right:0;width:180px">$ulink</td>
	<td class="b n1" style="border-left:0">
		<table width="100%">
			<td class="nb">(post deleted)</td>
			<td class="nb right">$postlinks</td>
		</table>
	</td>
</tr></table>
HTML;
		return $text;
	}

	$postheaderrow = $threadlink = $postlinks = $revisionstr = '';

	$post['id'] = (isset($post['id']) ? $post['id'] : 0);

	if ($pthread)
		$threadlink = ", in <a href=\"thread.php?id=$pthread[id]\">" . esc($pthread['title']) . "</a>";

	if (isset($post['id']) && $post['id'])
		$postlinks = "<a href=\"thread.php?pid=$post[id]#$post[id]\">Link</a>"; // headlinks for posts

	if (isset($post['revision']) && $post['revision'] >= 2)
		$revisionstr = " (rev. {$post['revision']} of " . date($dateformat, $post['ptdate']) . " by " . userlink_by_id($post['ptuser']) . ")";

	// I have no way to tell if it's closed (or otherwise impostable (hah)) so I can't hide it in those circumstances...
	if (isset($post['thread']) && $post['id'] && $userdata['id'] != 0) {
		$postlinks .= ($postlinks ? ' &bull; ' : '') . "<a href=\"newreply.php?id=$post[thread]&pid=$post[id]\">Reply</a>";
	}

	// "Edit" link for admins or post owners, but not banned users
	if (isset($post['thread']) && canEditPost($post) && $post['id'])
		$postlinks.=($postlinks ? ' &bull; ' : '') . "<a href=\"editpost.php?pid=$post[id]\">Edit</a>";

	if (isset($post['thread']) && isset($post['id']) && canDeleteForumPosts(getForumByThread($post['thread'])))
		$postlinks.=($postlinks ? ' &bull; ' : '') . "<a href=\"editpost.php?pid=".$post['id']."&act=delete\">Delete</a>";

	if (isset($post['thread']) && $post['id'])
		$postlinks.=" &bull; ID: $post[id]";

	if (isset($post['maxrevision']) && isset($post['thread']) && hasPerm('view-post-history') && $post['maxrevision'] > 1) {
		$revisionstr.=" &bull; Revision ";
		for ($i = 1; $i <= $post['maxrevision']; $i++)
			$revisionstr .= "<a href=\"thread.php?pid=$post[id]&pin=$post[id]&rev=$i#$post[id]\">$i</a> ";
	}

	$ulink = userlink($post, 'u');
	$pdate = date($dateformat, $post['date']);
	$lastpost = ($post['ulastpost'] ? timeunits(time() - $post['ulastpost']) : 'none');
	$lastview = timeunits(time() - $post['ulastview']);
	$picture = ($post['uavatar'] ? "<img src=\"userpic/{$post['uid']}\">" : '');
	if ($post['usignature']) {
		$post['usignature'] = '<div class="siggy">' . postfilter($post['usignature']) . '</div>';
	}
	$utitle = $post['utitle'];
	$ujoined = date('Y-m-d', $post['ujoined']);
	$posttext = postfilter($post['text']);
	$text = <<<HTML
<table class="c1 threadpost" id="{$post['id']}">
	$postheaderrow
	<tr>
		<td class="b n1 topbar_1">$ulink</td>
		<td class="b n1 topbar_2 fullwidth">
			<table class="fullwidth">
				<tr><td class="nb">Posted on $pdate$threadlink $revisionstr</td><td class="nb right">$postlinks</td></tr>
			</table>
		</td>
	</tr><tr valign="top">
		<td class="b n1 sidebar">
			$utitle
			$picture
			<br>Posts: {$post['uposts']}
			<br>
			<br>Since: $ujoined
			<br>
			<br>Last post: $lastpost
			<br>Last view: $lastview
		</td>
		<td class="b n2 mainbar" id="post_{$post['id']}">$posttext{$post['usignature']}</td>
	</tr>
</table>
HTML;

	return $text;
}
