<?php
class Telemetry
{
    /**
     * Telemetrie ist deaktiviert fuer Self-Hosted Installationen.
     * Keine Daten werden an externe Server gesendet.
     */
    public function logTelemetry()
    {
        return;
    }
}
