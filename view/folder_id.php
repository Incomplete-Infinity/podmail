<?php

namespace podmail;

if ($char_id == 0) return $response->withStatus(302)->withRedirect('/');

$db = $config['db'];
$id = (int) $args['id'];
$page = (int) @$args['page'];

$row = $db->queryDoc('scopes', ['scope' => 'esi-mail.read_mail.v1', 'character_id' => $char_id]);
if ($row === null) return $response->withStatus(302)->withRedirect('/logout');
if (!isset($row['labels'])) return $response;

$labels = $row['labels'];
$lists = $row['mail_lists'];
$folder = @$labels[$id] != null ? @$labels[$id] : @$lists[$id];
$folder['label_id'] = $id;
if (@$folder['name'] == null) {
    $list = $db->queryDoc('information', ['type' => 'mailing_list_id', 'id' => $id]);
    $folder['name'] = strlen($list['name']) > 0 ? $list['name'] : "Unknown list $id";  
}

$filter = ['owner' => ['$in' => [$char_id]]/*, 'deleted' => ['$ne' => true]*/];
if ($id == 999999998) $filter['labels'] = [];
else if ($id == 999999997) {
    $filter['is_read'] = false;
    $filter['labels'] = ['$ne' => 999999999];
}
else if ($id != 0) $filter['labels'] = $id;
else $filter['labels'] = ['$ne' => 999999999];

$mails =  $db->query('mails', $filter, ['sort' => ['mail_id' => -1], 'limit' => 25, 'skip' => ($page * 25)]);
$count = $db->count('mails', $filter);
$max = ceil($count / 25);

$iterated = false;
if ($count == 0) {
    $row = $db->queryDoc('scopes', ['scope' => 'esi-mail.read_mail.v1', 'character_id' => $char_id]);
    $iterated = (bool) @$row['iterated'];
}

Info::addInfo($db, $mails);

return $app->view->render($response, 'mails.html', ['folder' => $folder, 'mails' => $mails, '$id' => $id, 'count' => $count, 'page' => (1 + $page), 'max' => $max, 'iterated' => $iterated]);
