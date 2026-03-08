<?php
/**
 * Projekt-Kommentare (strukturiert)
 *
 * Eigenstaendige Kommentare pro Projekt mit Benutzer-Zuordnung.
 * Ergaenzt das bestehende QuickComment-System im AuditLog.
 *
 * Tabellen:
 *   project_comments - Kommentare
 */
class ProjectCommentService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Kommentar hinzufuegen
     */
    public function addComment(int $projectId, int $userId, string $comment): int
    {
        return $this->db->insert('project_comments', [
            'projects_id' => $projectId,
            'users_id' => $userId,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Kommentare eines Projekts abrufen (neueste zuerst)
     */
    public function getComments(int $projectId): array
    {
        $this->db->where('project_comments.projects_id', $projectId);
        $this->db->join('users', 'project_comments.users_id = users.users_userid', 'LEFT');
        $this->db->orderBy('project_comments.created_at', 'DESC');
        return $this->db->get('project_comments', null, [
            'project_comments.*',
            'users.users_name1',
            'users.users_name2',
            'users.users_email',
        ]) ?: [];
    }

    /**
     * Eigenen Kommentar loeschen
     */
    public function deleteComment(int $commentId, int $userId): bool
    {
        $this->db->where('id', $commentId);
        $this->db->where('users_id', $userId);
        return $this->db->delete('project_comments');
    }
}
