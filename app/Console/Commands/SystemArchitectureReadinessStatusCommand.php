<?php

namespace App\Console\Commands;

use App\Services\Roadmap\ArchitectureReadinessAssessmentService;
use Illuminate\Console\Command;

class SystemArchitectureReadinessStatusCommand extends Command
{
    protected $signature = 'system:architecture-readiness-status';

    protected $description = 'Display safe v1.1 architecture completion and enterprise readiness final assessment status.';

    public function handle(ArchitectureReadinessAssessmentService $assessment): int
    {
        $report = $assessment->report();
        $scores = $report['scores'];
        $closure = $report['roadmap_closure'];

        $this->info('v1.1 architecture readiness final assessment');
        $this->line('Core Product Readiness Score: '.$scores['core_product_readiness']);
        $this->line('Production Readiness Score: '.$scores['production_readiness']);
        $this->line('Security Readiness Score: '.$scores['security_readiness']);
        $this->line('API Readiness Score: '.$scores['api_readiness']);
        $this->line('Automation Readiness Score: '.$scores['automation_readiness']);
        $this->line('Enterprise Readiness Score: '.$scores['enterprise_readiness']);
        $this->line('Governance Readiness Score: '.$scores['governance_readiness']);
        $this->line('Identity Readiness Score: '.$scores['identity_readiness']);
        $this->line('Authorization Readiness Score: '.$scores['authorization_readiness']);
        $this->line('Billing Readiness Score: '.$scores['billing_readiness']);
        $this->line('Multi-Tenant Readiness Score: '.$scores['multi_tenant_readiness']);
        $this->line('Overall Architecture Readiness Score: '.$scores['overall_architecture_readiness']);
        $this->line('Critical gaps: '.count($report['critical_gaps']));
        $this->line('STEP01-STEP75 completed');
        $this->line($closure['status']);
        $this->line('Roadmap Closed');
        $this->line($closure['future_work_rule']);

        return self::SUCCESS;
    }
}
