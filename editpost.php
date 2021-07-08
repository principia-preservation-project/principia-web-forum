<?php
require('lib/common.php');

$_GET['act'] = (isset($_GET['act']) ? $_GET['act'] : 'needle');
$_POST['action'] = (isset($_POST['action']) ? $_POST['action'] : '');

if ($action = $_POST['action']) {
	$pid = $_POST['pid'];
} else {
	$pid = $_GET['pid'];
}

if ($_GET['act'] == 'delete' || $_GET['act'] == 'undelete') {
	$action = $_GET['act'];
	$pid = $pid;
}

needsLogin();

$thread = $sql->fetch("SELECT p.user puser, t.*, f.title ftitle, f.private fprivate, f.readonly freadonly FROM posts p LEFT JOIN threads t ON t.id = p.thread "
	."LEFT JOIN forums f ON f.id=t.forum WHERE p.id = ? AND (t.forum IN ".forumsWithViewPerm().")", [$pid]);

if (!$thread) $pid = 0;

if ($thread['closed'] && !canCreateForumPosts($thread['forum'])) {
	error("403", "You can't edit a post in closed threads!");
} else if (!canEditPost(['user' => $thread['puser'], 'tforum' => $thread['forum']])) {
	error("403", "You do not have permission to edit this post.");
} else if ($pid == -1) {
	error("404", "Invalid post ID.");
}

$post = $sql->fetch("SELECT u.id, p.user, pt.text FROM posts p LEFT JOIN poststext pt ON p.id=pt.id "
		."JOIN (SELECT id,MAX(revision) toprev FROM poststext GROUP BY id) as pt2 ON pt2.id = pt.id AND pt2.toprev = pt.revision "
		."LEFT JOIN principia.users u ON p.user = u.id WHERE p.id = ?", [$pid]);

if (!isset($post)) $err = "Post doesn't exist.";

if ($action == 'Submit') {
	if ($post['text'] == $_POST['message']) {
		error("400", "No changes detected.");
	}

	$rev = $sql->result("SELECT MAX(revision) FROM poststext WHERE id = ?", [$pid]) + 1;

	$sql->query("INSERT INTO poststext (id,text,revision,user,date) VALUES (?,?,?,?,?)", [$pid,$_POST['message'],$rev,$userdata['id'],time()]);

	redirect("thread.php?pid=$pid#edit");
} else if ($action == 'delete' || $action == 'undelete') {

	if (!(canDeleteForumPosts($thread['forum']))) {
		error("403", "You do not have the permission to do this.");
	} else {
		$sql->query("UPDATE posts SET deleted = ? WHERE id = ?", [($action == 'delete' ? 1 : 0), $pid]);
		redirect("thread.php?pid=$pid#edit");
	}
}

$topbot = [
	'breadcrumb' => [
		['href' => './', 'title' => 'Main'],
		['href' => "forum.php?id={$thread['forum']}", 'title' => $thread['ftitle']],
		['href' => "thread.php?id={$thread['id']}", 'title' => esc($thread['title'])]
	],
	'title' => 'Edit post'
];

$euser = $sql->fetch("SELECT * FROM principia.users WHERE id = ?", [$post['id']]);
$post['date'] = time();
$post['text'] = ($action == 'Preview' ? $_POST['message'] : $post['text']);
foreach ($euser as $field => $val)
	$post['u'.$field] = $val;
$post['ulastpost'] = time();

if ($action == 'Preview') {
	$topbot['title'] .= ' (Preview)';
}

$twig = _twigloader();
echo $twig->render('editpost.twig', [
	'post' => $post,
	'topbot' => $topbot,
	'action' => $action,
	'pid' => $pid
]);