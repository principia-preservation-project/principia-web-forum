<?php
require('lib/common.php');

needs_login();

$page = (isset($_GET['page']) ? $_GET['page'] : null);
if (!$page) $page = 1;
$view = (isset($_GET['view']) ? $_GET['view'] : 'read');

if ($view == 'sent') {
	$fieldn = 'to';
	$fieldn2 = 'from';
	$sent = true;
} else {
	$fieldn = 'from';
	$fieldn2 = 'to';
	$sent = false;
}

$id = (has_perm('view-user-pms') ? (isset($_GET['id']) ? $_GET['id'] : null) : 0);

if ($id === 0 && $id !== null) error("Error", "You are not allowed to do this!");

$showdel = isset($_GET['showdel']);

if (isset($_GET['action']) && $_GET['action'] == "del") {
	$owner = $sql->result("SELECT user$fieldn2 FROM pmsgs WHERE id = ?", [$id]);
	if (has_perm('delete-user-pms') || ($owner == $userdata['id'] && has_perm('delete-own-pms'))) {
		$sql->query("UPDATE pmsgs SET del_$fieldn2 = ? WHERE id = ?", [!$showdel, $id]);
	} else {
		error("Error", "You are not allowed to (un)delete that message.");
	}
	$id = 0;
}

$ptitle = 'Private messages' . ($sent ? ' (sent)' : '');
if ($id && has_perm('view-user-pms')) {
	$user = $sql->fetch("SELECT id,name,group_id FROM principia.users WHERE id = ?", [$id]);
	if ($user == null) error("Error", "User doesn't exist.");
	//pageheader($user['name']."'s ".strtolower($ptitle));
	$title = userlink($user)."'s ".strtolower($ptitle);
} else {
	$id = $userdata['id'];
	//pageheader($ptitle);
	$title = $ptitle;
}

$pmsgc = $sql->result("SELECT COUNT(*) FROM pmsgs WHERE user$fieldn2 = ? AND del_$fieldn2 = ?", [$id, $showdel]);
$pmsgs = $sql->query("SELECT ".userfields('u', 'u').", p.* FROM pmsgs p "
					."LEFT JOIN principia.users u ON u.id = p.user$fieldn "
					."WHERE p.user$fieldn2 = ? "
				."AND del_$fieldn2 = ? "
					."ORDER BY p.unread DESC, p.date DESC "
					."LIMIT " . (($page - 1) * $userdata['tpp']) . ", " . $userdata['tpp'],
				[$id, $showdel]);

$topbot = [
	'breadcrumb' => [['href' => './', 'title' => 'Main']],
	'title' => $title
];

if ($sent)
	$topbot['actions'] = [['href' => 'private.php'.($id != $userdata['id'] ? "?id=$id&" : ''), 'title' => "View received"]];
else
	$topbot['actions'] = [['href' => 'private.php?'.($id != $userdata['id'] ? "id=$id&" : '').'view=sent', 'title' => "View sent"]];

$topbot['actions'][] = ['href' => 'sendprivate.php', 'title' => 'Send new'];

if ($pmsgc <= $userdata['tpp'])
	$fpagelist = '<br>';
else {
	if ($id != $userdata['id'])
		$furl = "private.php?id=$id&view=$view";
	else
		$furl = "private.php?view=$view";
	$fpagelist = pagelist($pmsgc, $userdata['tpp'], $furl, $page).'<br>';
}

$twig = _twigloader();
echo $twig->render('private.twig', [
	'id' => $id,
	'pmsgs' => $pmsgs,
	'topbot' => $topbot,
	'fieldn' => $fieldn,
	'fpagelist' => $fpagelist
]);
