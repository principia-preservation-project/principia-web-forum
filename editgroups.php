<?php
require('lib/common.php');

if (!has_perm('edit-groups')) error('Error', 'You have no permissions to do this!');

$act = (isset($_GET['act']) ? $_GET['act'] : '');
$errmsg = '';
$caneditperms = has_perm('edit-permissions');

if ($act == 'delete') {
	$id = $_GET['id'];
	$group = $sql->fetch("SELECT * FROM groups WHERE id = ?", [$id]);

	if (!$group)
		$errmsg = 'Cannot delete group: invalid group ID';
	else {
		$usercount = $sql->result("SELECT COUNT(*) FROM principia.users WHERE group_id = ?", [$group['id']]);
		if ($usercount > 0) $errmsg = 'This group cannot be deleted because it contains users';

		if (!$errmsg && !$caneditperms) {
			$permcount = $sql->result("SELECT COUNT(*) FROM x_perm WHERE x_type = 'group' AND x_id = ?", [$group['id']]);
			if ($permcount > 0) $errmsg = 'This group cannot be deleted because it has permissions attached and you may not edit permissions.';
		}

		if (!$errmsg) {
			$sql->query("DELETE FROM groups WHERE id = ?", [$group['id']]);
			$sql->query("DELETE FROM x_perm WHERE x_type = 'group' AND x_id = ?", [$group['id']]);
			$sql->query("UPDATE groups SET inherit_group_id = 0 WHERE inherit_group_id = ?", [$group['id']]);
			redirect('editgroups.php');
		}
	}
} else if (isset($_POST['submit']) && ($act == 'new' || $act == 'edit')) {
	$title = trim($_POST['title']);

	$parentid = $_POST['inherit_group_id'];
	if ($parentid < 0 || $parentid > $sql->result("SELECT MAX(id) FROM groups")) $parentid = 0;

	if ($act == 'edit') {
		$recurcheck = [$_GET['id']];
		$pid = $parentid;
		while ($pid > 0) {
			if ($pid == $recurcheck[0]) {
				$errmsg = 'Endless recursion detected, choose another parent for this group';
				break;
			}

			$recurcheck[] = $pid;
			$pid = $sql->result("SELECT inherit_group_id FROM groups WHERE id = ?",[$pid]);
		}
	}

	if (!$errmsg) {
		$sortorder = (int)$_POST['sortorder'];

		$visible = $_POST['visible'] ? 1:0;

		if (empty($title))
			$errmsg = 'You must enter a name for the group.';
		else {
			$values = [$title, $_POST['nc'], $parentid, $sortorder, $visible];

			if ($act == 'new')
				$sql->query("INSERT INTO groups VALUES (0,?,?,?,?,?)", $values);
			else {
				$values[] = $_GET['id'];
				$sql->query("UPDATE groups SET title = ?,nc = ?,inherit_group_id = ?,sortorder = ?,visible = ? WHERE id = ?", $values);
			}
			redirect('editgroups.php');
		}
	}
}



if ($act == 'new' || $act == 'edit') {
	$pagebar = [
		'breadcrumb' => [['href'=>'./', 'title'=>'Main'], ['href'=>'editgroups.php', 'title'=>'Edit groups']],
		'title' => '',
		'actions' => [['href'=>'editgroups.php?act=new', 'title'=>'New group']],
		'message' => $errmsg
	];

	if ($act == 'new') {
		$group = ['id'=>0, 'title'=>'', 'nc'=>'', 'inherit_group_id'=>0, 'sortorder'=>0, 'visible'=>0];
		$pagebar['title'] = 'New group';
	} else {
		$group = $sql->fetch("SELECT * FROM groups WHERE id = ?",[$_GET['id']]);
		if (!$group) error("Error", "Invalid group ID.");
		$pagebar['title'] = 'Edit group';
	}

	pageheader('Edit groups');

	if ($group) {
		$grouplist = [0 => '(none)'];
		$allgroups = $sql->query("SELECT id,title FROM groups WHERE id != ? ORDER BY sortorder",[$group['id']]);
		while ($g = $allgroups->fetch())
			$grouplist[$g['id']] = $g['title'];

		RenderPageBar($pagebar);
		echo '<br><form method="post"><table class="c1">' .
		catheader('Group Settings')
.	fieldrow('Name', fieldinput(50, 255, 'title', $group['title']))
.	fieldrow('Parent group', fieldselect('inherit_group_id', $group['inherit_group_id'], $grouplist))
.	fieldrow('Sort order', fieldinput(4, 8, 'sortorder', $group['sortorder']))
.	fieldrow('Visibility', fieldoption('visible', $group['visible'], ['Invisible', 'Visible']))
.	fieldrow('Color', fieldinput(6,6,'nc',$group['nc']))
.	'<tr class="n1"><td class="b"></td><td class="b"><input type="submit" name="submit" value="Apply changes"></td></table></form><br>';
		$pagebar['message'] = '';
		RenderPageBar($pagebar);
	}
} else {
	$pagebar = [
		'breadcrumb' => [['href'=>'./', 'title'=>'Main']],
		'title' => 'Edit groups',
		'actions' => [['href'=>'editgroups.php?act=new', 'title'=>'New group']],
		'message' => $errmsg
	];

	pageheader('Edit groups');

	RenderPageBar($pagebar);
	echo '<br>';

	$header = [
		'sort' => ['name'=>'Order', 'width'=>'32px', 'align'=>'center'],
		'id' => ['name'=>'#', 'width'=>'32px', 'align'=>'center'],
		'name' => ['name'=>'Name', 'align'=>'center'],
		'parent' => ['name'=>'Parent group', 'width' => '240px', 'align'=>'center'],
		'actions' => ['name'=>'', 'width'=>'240px', 'align'=>'right'],
	];

	$groups = $sql->query("SELECT g.*, pg.title parenttitle FROM groups g LEFT JOIN groups pg ON pg.id=g.inherit_group_id ORDER BY sortorder");
	$data = [];

	while ($group = $groups->fetch()) {
		$name = esc($group['title']);
		if ($group['visible']) $name = "<strong>{$name}</strong>";
		if ($group['nc']) $name = str_replace('<strong>', "<strong style=\"color: #{$group['nc']};\">", $name);

		$actions = [];
		if ($caneditperms) $actions[] = ['href'=>'editperms.php?gid='.$group['id'], 'title'=>'Edit perms'];
		$actions[] = ['href'=>'editgroups.php?act=edit&id='.$group['id'], 'title'=>'Edit'];
		if ($caneditperms && $group['id'] > 7)
			$actions[] = ['href'=>'editgroups.php?act=delete&id='.$group['id'], 'title'=>'Delete',
				'confirm'=>'Are you sure you want to delete the group "'.esc($group['title']).'"?'];

		$data[] = [
			'sort' => $group['sortorder'],
			'id' => $group['id'],
			'name' => $name,
			'parent' => $group['parenttitle'] ? esc($group['parenttitle']) : '<small>(none)</small>',
			'actions' => RenderActions($actions,true),
		];
	}

	RenderTable($data, $header);
	echo '<br>';
	$pagebar['message'] = '';
	RenderPageBar($pagebar);
}

pagefooter();