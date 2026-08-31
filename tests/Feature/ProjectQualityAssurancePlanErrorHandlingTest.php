<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProjectQualityAssurancePlanErrorHandlingTest extends TestCase
{
    public function test_required_field_errors_use_user_friendly_labels(): void
    {
        $this->withWorkflowActor('PQP-TEST')
            ->postJson('/api/project_quality_assurance_plans', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.prepared_by_tl.0', 'Please select Prepared by (TL).')
            ->assertJsonPath('errors.approved_by_di.0', 'Please select Approved by (DI).')
            ->assertJsonPath('errors.acknowledged_by_vve.0', 'Please select Acknowledged by.')
            ->assertJsonPath('errors.project_name.0', 'Please select a project.')
            ->assertJsonPath('errors.project_no.0', 'Please select a project with a project number.');
    }

    public function test_system_failure_is_logged_without_exposing_database_details_to_the_user(): void
    {
        Log::spy();

        $response = $this->withWorkflowActor('PQP-TEST')
            ->postJson('/api/project_quality_assurance_plans', [
                'revision' => 'Rev. 01',
                'date' => '2026-08-31',
                'prepared_by_tl' => 'TL, Test Lead',
                'approved_by_di' => 'DI, Test Director',
                'acknowledged_by_vve' => 'VVE, Test Reviewer',
                'project_name' => 'Controlled error test',
                'project_no' => 'PQP-ERROR-TEST',
            ]);

        $response
            ->assertStatus(500)
            ->assertJsonPath(
                'message',
                'The system could not save the Project Quality Plan. Please try again. If the problem continues, contact support.'
            );

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
        $this->assertStringNotContainsString('project_quality_assurance_plans', (string) $response->json('message'));
        Log::shouldHaveReceived('error')
            ->once()
            ->with(\Mockery::on(function ($message) {
                return strpos((string) $message, 'PQA store failed:') === 0;
            }));
    }

    private function withWorkflowActor(string $employeeCode): self
    {
        $now = time();
        $token = JWT::encode([
            'iss' => 'key',
            'aud' => 1,
            'lun' => (object) [
                'id' => 1,
                'username' => strtolower($employeeCode),
                'employee_code' => $employeeCode,
            ],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
        ], 'key');

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }
}
