<?php

/**
 * Example: Transport Module Integration with Sustainability
 *
 * This file shows how to integrate the sustainability module with tour completion.
 * Add similar code to your tour completion endpoint.
 *
 * Location: After tour status is updated to 'completed' in your tour completion handler
 */

// Example integration point (add to your tour completion logic):

/*
// In your transport tour completion endpoint:

if ($_POST['action'] === 'mark_complete' && $tourId) {
    // ... existing tour completion logic ...

    // Update tour status
    $db->where('id', $tourId);
    $db->where('instances_id', $instanceId);
    $db->update('transport_tours', [
        'status' => 'completed',
        'completed_at' => date('Y-m-d H:i:s')
    ]);

    // NEW: Log CO2 emissions to sustainability module
    require_once dirname(__DIR__) . '/../services/SustainabilityService.php';
    $sustainabilityService = new SustainabilityService($db);

    try {
        $sustainabilityService->autoLogFromTour($tourId, $instanceId);
        $_SESSION['alerts'][] = [
            'type' => 'success',
            'message' => 'Tour abgeschlossen. CO₂-Emissionen wurden automatisch erfasst.'
        ];
    } catch (Exception $e) {
        $_SESSION['alerts'][] = [
            'type' => 'warning',
            'message' => 'Tour abgeschlossen, aber CO₂-Erfassung fehlgeschlagen: ' . $e->getMessage()
        ];
    }

    // ... rest of response ...
}
*/

?>

<!--
=== IMPLEMENTATION CHECKLIST FOR TRANSPORT MODULE ===

1. Ensure your transport_tours table has these columns:
   - distance_km (DECIMAL)
   - vehicle_type (VARCHAR) - e.g., 'van', 'truck', 'car'
   - project_id (INTEGER, nullable)
   - status (VARCHAR or ENUM)

2. In your tour completion endpoint (e.g., /src/api/transport/complete.php):
   a. After marking tour as 'completed'
   b. Load the SustainabilityService
   c. Call: $sustainabilityService->autoLogFromTour($tourId, $instanceId);
   d. Wrap in try-catch to avoid blocking tour completion

3. The autoLogFromTour method will:
   - Fetch the tour record
   - Extract: distance_km, vehicle_type, project_id
   - Calculate CO2 based on instance configuration
   - Insert into sustainability_transport_log
   - Return silently if tour not found or distance is 0

4. Optional: Display to user that CO₂ has been logged

EXAMPLE CODE:
-->

<?php
// Minimal working example:

function logTourSustainability($tourId, $instanceId, $db) {
    require_once __DIR__ . '/../../services/SustainabilityService.php';
    $sustainabilityService = new SustainabilityService($db);

    try {
        $sustainabilityService->autoLogFromTour($tourId, $instanceId);
        return ['success' => true, 'message' => 'CO2 logged'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Usage in your tour completion handler:
// $result = logTourSustainability($tourId, $instanceId, $db);
?>
