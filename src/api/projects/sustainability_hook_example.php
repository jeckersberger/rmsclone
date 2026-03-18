<?php

/**
 * Example: Project Module Integration with Sustainability
 *
 * This file shows how to integrate the sustainability module with project completion.
 * Add similar code to your project completion endpoint.
 *
 * Location: After project status is updated to 'closed' in your project completion handler
 */

// Example integration point (add to your project completion logic):

/*
// In your project completion endpoint:

if ($_POST['action'] === 'close_project' && $projectId) {
    // ... existing project closure logic ...

    // Update project status
    $db->where('id', $projectId);
    $db->where('instances_id', $instanceId);
    $db->update('projects', [
        'status' => 'closed',
        'closed_at' => date('Y-m-d H:i:s')
    ]);

    // NEW: Log energy consumption to sustainability module
    require_once dirname(__DIR__) . '/../services/SustainabilityService.php';
    $sustainabilityService = new SustainabilityService($db);

    try {
        $sustainabilityService->autoLogFromProject($projectId, $instanceId);
        $_SESSION['alerts'][] = [
            'type' => 'success',
            'message' => 'Projekt abgeschlossen. Energieverbrauch wurde automatisch berechnet.'
        ];
    } catch (Exception $e) {
        $_SESSION['alerts'][] = [
            'type' => 'warning',
            'message' => 'Projekt abgeschlossen, aber Energieberechnung fehlgeschlagen: ' . $e->getMessage()
        ];
    }

    // ... rest of response ...
}
*/

?>

<!--
=== IMPLEMENTATION CHECKLIST FOR PROJECT MODULE ===

1. Ensure your projects table has these columns:
   - id (INTEGER PRIMARY KEY)
   - start_date (DATE)
   - end_date (DATE)
   - status (VARCHAR or ENUM)
   - instances_id (INTEGER)

2. Ensure your asset_equipment / projects_assets junction table exists:
   - projects_assets(projects_id, assets_id, qty, instances_id)

3. Ensure your asset/equipment table has these columns:
   - id (INTEGER PRIMARY KEY)
   - asset_name (VARCHAR)
   - power_rating (DECIMAL) - in kW, e.g., 0.5 for 500W
   - instances_id (INTEGER)

4. In your project completion endpoint (e.g., /src/api/projects/complete.php):
   a. After marking project as 'closed'
   b. Load the SustainabilityService
   c. Call: $sustainabilityService->autoLogFromProject($projectId, $instanceId);
   d. Wrap in try-catch to avoid blocking project closure

5. The autoLogFromProject method will:
   - Fetch the project record (start_date, end_date)
   - Calculate duration in hours
   - Fetch all equipment assigned to the project
   - Sum: equipment power (kW) × duration (hours) = energy (kWh)
   - Calculate CO2 based on instance energy configuration
   - Insert into sustainability_energy_log
   - Return silently if project not found or no equipment found

6. Optional: Display to user that energy consumption has been logged

ENERGY CALCULATION EXAMPLE:
Project: 2026-03-01 to 2026-03-15 (14 days = 336 hours)
Equipment:
  - 2× Projector @ 0.5 kW each = 1.0 kW
  - 4× LED Light @ 0.2 kW each = 0.8 kW
Total power: 1.8 kW
Total energy: 1.8 × 336 = 604.8 kWh
CO2 @ 0.42 kg/kWh = 254.016 kg

EXAMPLE CODE:
-->

<?php
// Minimal working example:

function logProjectSustainability($projectId, $instanceId, $db) {
    require_once __DIR__ . '/../../services/SustainabilityService.php';
    $sustainabilityService = new SustainabilityService($db);

    try {
        $sustainabilityService->autoLogFromProject($projectId, $instanceId);
        return ['success' => true, 'message' => 'Energy logged'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Usage in your project completion handler:
// $result = logProjectSustainability($projectId, $instanceId, $db);
?>

<!--
=== TROUBLESHOOTING ===

Problem: No energy is being logged
→ Check that equipment has power_rating > 0
→ Check that equipment is linked to project in projects_assets
→ Check that project has start_date and end_date set

Problem: Wrong energy calculation
→ Verify: start_date is before end_date
→ Verify: power_rating is in kW (not watts)
→ Check: project duration calculation (hours not days)

Problem: Multiple energy logs for same project
→ autoLogFromProject checks for existing entries
→ If called multiple times, only first call creates log
→ Subsequent calls return silently (idempotent)
-->
