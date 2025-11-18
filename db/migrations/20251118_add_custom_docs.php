<?php
use Phinx\Migration\AbstractMigration;
class AddCustomDocs extends AbstractMigration {
  public function change() {
    if (!$this->hasTable('document_templates')) {
      $this->table('document_templates')
        ->addColumn('instances_id','integer')
        ->addColumn('type','enum',['values'=>['invoice','quote','delivery_note']])
        ->addColumn('key','string',['limit'=>80])   // z.B. invoice_de_kur
        ->addColumn('name','string',['limit'=>120])
        ->addColumn('language','string',['default'=>'de-DE'])
        ->addColumn('css','text',['null'=>true])
        ->addColumn('twig_html','mediumtext')
        ->addColumn('is_default','boolean',['default'=>0])
        ->addTimestamps()
        ->addIndex(['instances_id','type','key'],['unique'=>true])
        ->create();
    }
    if (!$this->hasTable('document_sequences')) {
      $this->table('document_sequences')
        ->addColumn('instances_id','integer')
        ->addColumn('type','enum',['values'=>['invoice','quote','delivery_note']])
        ->addColumn('name','string',['limit'=>80])
        ->addColumn('prefix','string',['limit'=>40,'default'=>''])
        ->addColumn('padding','integer',['default'=>4])
        ->addColumn('suffix','string',['limit'=>40,'default'=>''])
        ->addColumn('reset_period','enum',['values'=>['never','yearly','monthly'],'default'=>'yearly'])
        ->addColumn('current_number','integer',['default'=>0])
        ->addColumn('last_reset_at','datetime',['null'=>true])
        ->addTimestamps()
        ->addIndex(['instances_id','type','name'],['unique'=>true])
        ->create();
    }
    if (!$this->hasTable('document_exports')) {
      $this->table('document_exports')
        ->addColumn('instances_id','integer')
        ->addColumn('projects_id','integer')
        ->addColumn('type','enum',['values'=>['invoice','quote','delivery_note']])
        ->addColumn('template_id','integer')
        ->addColumn('sequence_id','integer',['null'=>true])
        ->addColumn('doc_number','string',['limit'=>64])
        ->addColumn('language','string',['default'=>'de-DE'])
        ->addColumn('currency','string',['default'=>'EUR'])
        ->addColumn('totals_json','text')
        ->addColumn('snapshot_json','mediumtext')
        ->addColumn('s3files_id','integer',['null'=>true])
        ->addColumn('generated_by','integer')
        ->addColumn('generated_at','datetime',['default'=>'CURRENT_TIMESTAMP'])
        ->addTimestamps()
        ->addIndex(['instances_id','projects_id'])
        ->create();
    }
  }
}
