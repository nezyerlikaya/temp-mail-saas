<?php

namespace App\Console\Commands;

use App\Services\Roadmap\OrganizationRoadmapPlanningService;
use Illuminate\Console\Command;

class SystemOrganizationRoadmapStatusCommand extends Command
{
    protected $signature = 'system:organization-roadmap-status';

    protected $description = 'Display safe v1.1 enterprise organization roadmap planning status.';

    public function handle(OrganizationRoadmapPlanningService $planning): int
    {
        $report = $planning->report();
        $scores = $report['scores'];

        $this->info('v1.1 organization roadmap summary');
        $this->line('Enterprise Readiness Score: '.$scores['enterprise_readiness']);
        $this->line('Organization Readiness Score: '.$scores['organization_readiness']);
        $this->line('Governance Readiness Score: '.$scores['governance_readiness']);
        $this->line('Security Readiness Score: '.$scores['security_readiness']);
        $this->line('Multi-Tenant Readiness Score: '.$scores['multi_tenant_readiness']);
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('Recommended STEP67: '.$report['step67']['recommended_next_phase']);

        return self::SUCCESS;
    }
}
