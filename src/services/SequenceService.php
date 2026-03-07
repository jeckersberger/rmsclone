<?php
class SequenceService {
    /**
     * Naechste lueckenlose Dokumentnummer generieren (GoBD-konform)
     *
     * Verwendet Row-Level Locking (SELECT ... FOR UPDATE) um bei gleichzeitigen
     * Anfragen keine Nummern zu ueberspringen oder doppelt zu vergeben.
     * Jede vergebene Nummer wird im document_sequence_log protokolliert.
     *
     * @param mixed  $db         Datenbank-Instanz
     * @param int    $instanceId Instance-ID
     * @param string $type       Dokumenttyp (invoice, quote, delivery_note)
     * @param int|null $userId   User-ID (fuer Audit-Log)
     * @return string Formatierte Dokumentnummer (z.B. RE-2026-0001)
     */
    public static function next($db, int $instanceId, string $type, ?int $userId = null): string {
        // Row-Level Lock fuer atomare Nummernvergabe
        $lockSql = "SELECT * FROM document_sequences WHERE instances_id = ? AND type = ? ORDER BY id ASC LIMIT 1 FOR UPDATE";

        // Transaktion starten fuer atomare Operation
        $db->startTransaction();

        try {
            $seqRows = $db->rawQuery($lockSql, [$instanceId, $type]);
            $seq = $seqRows ? $seqRows[0] : null;

            if (!$seq) {
                // Default-Sequenzen einmalig anlegen
                $name = 'DE-Standard';
                $patterns = [
                    'invoice' => ['prefix'=>'RE-'.date('Y').'-','padding'=>4,'suffix'=>''],
                    'quote'   => ['prefix'=>'AN-'.date('Y').'-','padding'=>4,'suffix'=>''],
                    'delivery_note' => ['prefix'=>'LS-'.date('Y').'-','padding'=>4,'suffix'=>''],
                ];
                $p = $patterns[$type] ?? $patterns['invoice'];
                $db->insert('document_sequences',[
                    'instances_id'=>$instanceId,'type'=>$type,'name'=>$name,
                    'prefix'=>$p['prefix'],'padding'=>$p['padding'],'suffix'=>$p['suffix'],
                    'reset_period'=>'yearly','current_number'=>0
                ]);
                $seqRows = $db->rawQuery($lockSql, [$instanceId, $type]);
                $seq = $seqRows[0];
            }

            // Yearly reset
            $nowY = (int)date('Y');
            if ($seq['reset_period']==='yearly' && (!isset($seq['last_reset_at']) || (int)date('Y', strtotime($seq['last_reset_at'])) !== $nowY)) {
                // Prefix mit aktuellem Jahr aktualisieren
                $prefixPatterns = [
                    'invoice' => 'RE-'.$nowY.'-',
                    'quote'   => 'AN-'.$nowY.'-',
                    'delivery_note' => 'LS-'.$nowY.'-',
                ];
                $newPrefix = $prefixPatterns[$type] ?? $seq['prefix'];
                $db->where('id', $seq['id']);
                $db->update('document_sequences', [
                    'current_number'=>0,
                    'last_reset_at'=>date('Y-m-d H:i:s'),
                    'prefix'=>$newPrefix
                ]);
                $seq['current_number'] = 0;
                $seq['prefix'] = $newPrefix;
            }

            $num = (int)$seq['current_number'] + 1;
            $db->where('id', $seq['id']);
            $db->update('document_sequences', ['current_number'=>$num]);

            $pad = str_pad((string)$num, (int)$seq['padding'], '0', STR_PAD_LEFT);
            $docNumber = (string)$seq['prefix'].$pad.$seq['suffix'];

            // Lueckenlose Protokollierung (GoBD-Pflicht)
            $db->insert('document_sequence_log', [
                'instances_id' => $instanceId,
                'sequence_type' => $type,
                'doc_number' => $docNumber,
                'sequence_value' => $num,
                'generated_by' => $userId,
                'generated_at' => date('Y-m-d H:i:s'),
                'used' => true,
            ]);

            $db->commit();
            return $docNumber;

        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Nummer als storniert markieren (nie loeschen, GoBD-konform)
     */
    public static function voidNumber($db, int $instanceId, string $docNumber, string $reason): bool {
        $db->where('instances_id', $instanceId);
        $db->where('doc_number', $docNumber);
        return $db->update('document_sequence_log', [
            'used' => false,
            'void_reason' => $reason,
        ]);
    }

    /**
     * Luecken-Pruefung: Gibt fehlende Nummern in der Sequenz zurueck
     */
    public static function findGaps($db, int $instanceId, string $type): array {
        $sql = "SELECT sequence_value FROM document_sequence_log
                WHERE instances_id = ? AND sequence_type = ?
                ORDER BY sequence_value ASC";
        $rows = $db->rawQuery($sql, [$instanceId, $type]) ?: [];

        $gaps = [];
        $expected = 1;
        foreach ($rows as $row) {
            $val = (int)$row['sequence_value'];
            while ($expected < $val) {
                $gaps[] = $expected;
                $expected++;
            }
            $expected = $val + 1;
        }
        return $gaps;
    }
}
