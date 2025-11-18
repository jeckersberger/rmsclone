<?php
require_once '../bootstrap.php';
Auth::requirePermission('BUSINESS:SETTINGS:EDIT');
$inst = Auth::instanceId();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $data = [
      'instances_id'=>$inst,
      'type'=>$_POST['type'],
      'key'=>$_POST['key'],
      'name'=>$_POST['name'],
      'language'=>$_POST['language'] ?? 'de-DE',
      'css'=>$_POST['css'] ?? '',
      'twig_html'=>$_POST['twig_html'] ?? '',
      'is_default'=>isset($_POST['is_default'])?1:0
    ];
    if ($id>0) { $db->update('document_templates',$data,'id='.$id); }
    else { $db->insert('document_templates',$data); }
    if ($data['is_default']) {
      $db->execute("UPDATE document_templates SET is_default=0 WHERE instances_id=? AND type=? AND id<>?",[$inst,$data['type'],$id?:$db->lastInsertId()]);
    }
  }
}
$tpls = $db->fetchAll("SELECT * FROM document_templates WHERE instances_id=? ORDER BY type,name",[$inst]);
?>
<!doctype html><html lang="de"><meta charset="utf-8"><title>Dokumentvorlagen (Custom Docs)</title>
<style>body{font:14px/1.4 system-ui,Segoe UI,Arial} textarea{width:100%;height:240px;font:12px/1.4 monospace} .row{display:flex;gap:16px} .col{flex:1} table{border-collapse:collapse;width:100%} td,th{border:1px solid #ddd;padding:6px}</style>
<h1>Dokumentvorlagen (Custom Docs)</h1>
<p>Hier kannst du HTML/Twig und CSS deiner Rechnung/Angebotsvorlagen bearbeiten.</p>
<div class="row">
  <div class="col">
    <h3>Vorlagen</h3>
    <table><tr><th>ID</th><th>Typ</th><th>Key</th><th>Name</th><th>Default</th></tr>
    <?php foreach ($tpls as $t): ?>
      <tr><td><?=$t['id']?></td><td><?=$t['type']?></td><td><?=$t['key']?></td><td><?=$t['name']?></td><td><?=$t['is_default']?'\u2713':''?></td></tr>
    <?php endforeach;?>
    </table>
  </div>
  <div class="col">
    <h3>Neu/Bearbeiten</h3>
    <form method="post">
      <label>Typ <select name="type"><option>invoice</option><option>quote</option><option>delivery_note</option></select></label><br>
      <label>Key <input name="key" placeholder="invoice_de_kur" required></label><br>
      <label>Name <input name="name" placeholder="Rechnung (DE – KUR)" required></label><br>
      <label>Sprache <input name="language" value="de-DE"></label><br>
      <label><input type="checkbox" name="is_default"> als Standard für Typ setzen</label><br>
      <label>CSS</label><br><textarea name="css"></textarea><br>
      <label>HTML (Twig)</label><br><textarea name="twig_html"></textarea><br>
      <button type="submit" name="save">Speichern</button>
    </form>
  </div>
</div>
