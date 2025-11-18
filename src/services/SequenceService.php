<?php
class SequenceService {
    public static function next($db, int $instanceId, string $type): string {
        $seq = $db->fetchRow("SELECT * FROM document_sequences WHERE instances_id=? AND type=? ORDER BY id ASC LIMIT 1", [$instanceId,$type]);
        if (!$seq) {
            // Default-Sequenzen einmalig anlegen
            $name = 'DE-Standard';
            $patterns = [
                'invoice' => ['prefix'=>'RE-'.date('Y').'-','padding'=>4,'suffix'=>''],
                'quote'   => ['prefix'=>'AN-'.date('Y').'-','padding'=>4,'suffix'=>''],
                'delivery_note' => ['prefix'=>'LS-'.date('Y').'-','padding'=>4,'suffix'=>''],
            ];
            $p = $patterns[$type];
            $db->insert('document_sequences',[
                'instances_id'=>$instanceId,'type'=>$type,'name'=>$name,
                'prefix'=>$p['prefix'],'padding'=>$p['padding'],'suffix'=>$p['suffix'],
                'reset_period'=>'yearly','current_number'=>0
            ]);
            $seq = $db->fetchRow("SELECT * FROM document_sequences WHERE instances_id=? AND type=? ORDER BY id ASC LIMIT 1", [$instanceId,$type]);
        }
        // Yearly reset
        $nowY = (int)date('Y');
        if ($seq['reset_period']==='yearly' && (!isset($seq['last_reset_at']) || (int)date('Y', strtotime($seq['last_reset_at'])) !== $nowY)) {
            $db->update('document_sequences',['current_number'=>0,'last_reset_at'=>date('Y-m-d H:i:s')],'id='.$seq['id']);
            $seq['current_number']=0;
        }
        $num = (int)$seq['current_number'] + 1;
        $db->update('document_sequences',['current_number'=>$num],'id='.$seq['id']);
        $pad = str_pad((string)$num, (int)$seq['padding'], '0', STR_PAD_LEFT);
        return (string)$seq['prefix'].$pad.$seq['suffix'];
    }
}
