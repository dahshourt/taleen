<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChangeRequest\ChangeRequestStatusService;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\Status;

class TestHandleNeedUpdateAction extends Command
{
    protected $signature = 'test:handle-need-update-action {cr_id?}';
    protected $description = 'Test the handleNeedUpdateAction function';

    public function handle()
    {
        $this->info('=== Testing handleNeedUpdateAction Function ===');

        $crId = $this->argument('cr_id');
        
        if ($crId) {
            $this->testSpecificCr($crId);
        } else {
            $this->runComprehensiveTests();
        }

        return 0;
    }

    private function runComprehensiveTests()
    {
        $this->line("\n1. Testing function existence...");
        $this->testFunctionExists();

        $this->line("\n2. Testing with invalid CR ID...");
        $this->testInvalidCrId();

        $this->line("\n3. Testing status name mappings...");
        $this->testStatusNameMappings();

        $this->line("\n4. Testing with existing CR...");
        $this->testWithExistingCr();

        $this->line("\n5. Testing database operations...");
        $this->testDatabaseOperations();

        $this->info("\n=== Test Summary ===");
        $this->info('Run with specific CR ID: php artisan test:handle-need-update-action {cr_id}');
    }

    private function testFunctionExists()
    {
        $service = new ChangeRequestStatusService();
        
        if (method_exists($service, 'handleNeedUpdateAction')) {
            $this->info('✓ Function handleNeedUpdateAction exists');
        } else {
            $this->error('✗ Function handleNeedUpdateAction does not exist');
        }
    }

    private function testInvalidCrId()
    {
        $service = new ChangeRequestStatusService();
        
        try {
            $service->handleNeedUpdateAction(999999);
            $this->error('✗ Should have thrown exception for invalid CR ID');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Change Request not found') !== false) {
                $this->info('✓ Correctly throws exception for invalid CR ID');
            } else {
                $this->error('✗ Wrong exception message: ' . $e->getMessage());
            }
        }
    }

    private function testStatusNameMappings()
    {
        $parallelStatusNames = [
            'Pending Agreed Scope Approval-SA',
            'Pending Agreed Scope Approval-Vendor',
            'Pending Agreed Scope Approval-Business',
            'Request Draft CR Doc'
        ];
        
        $allFound = true;
        foreach ($parallelStatusNames as $statusName) {
            $status = Status::where('status_name', $statusName)
                ->where('active', '1')
                ->first();
            
            if ($status) {
                $this->info("✓ Found status: {$statusName} (ID: {$status->id})");
            } else {
                $this->error("✗ Missing status: {$statusName}");
                $allFound = false;
            }
        }
        
        if ($allFound) {
            $this->info('✓ All required status names found');
        } else {
            $this->error('✗ Some status names are missing');
        }
    }

    private function testWithExistingCr()
    {
        $cr = Change_request::first();
        if (!$cr) {
            $this->warn('⚠ No Change Requests found in database');
            return;
        }

        $this->info("Testing with CR ID: {$cr->id}");
        
        $service = new ChangeRequestStatusService();
        
        try {
            $beforeCount = Change_request_statuse::where('cr_id', $cr->id)
                ->where('active', '1')
                ->count();
            
            $this->line("Before: {$beforeCount} active statuses");
            
            $result = $service->handleNeedUpdateAction($cr->id);
            
            $afterCount = Change_request_statuse::where('cr_id', $cr->id)
                ->where('active', '1')
                ->count();
            
            $this->line("After: {$afterCount} active statuses");
            $this->line("Function returned: " . ($result ? 'true' : 'false'));
            
            $this->info('✓ Function executed without errors');
            
        } catch (\Exception $e) {
            $this->error('✗ Error processing valid CR ID: ' . $e->getMessage());
        }
    }

    private function testDatabaseOperations()
    {
        try {
            $crCount = Change_request::count();
            $statusCount = Change_request_statuse::count();
            
            $this->info("✓ Change Requests table accessible ({$crCount} records)");
            $this->info("✓ Change Request Statuses table accessible ({$statusCount} records)");
            
            $activeStatuses = Change_request_statuse::active()->count();
            $this->info("✓ Active scope works ({$activeStatuses} active records)");
            
        } catch (\Exception $e) {
            $this->error('✗ Database operation failed: ' . $e->getMessage());
        }
    }

    private function testSpecificCr($crId)
    {
        $this->info("Testing specific CR ID: {$crId}");
        
        $cr = Change_request::find($crId);
        if (!$cr) {
            $this->error("✗ Change Request with ID {$crId} not found");
            return;
        }

        $this->info("Found CR: {$cr->cr_no}");
        $this->info("Current status: " . ($cr->status ? $cr->status->status_name : 'N/A'));

        // Check current active statuses
        $activeStatuses = Change_request_statuse::where('cr_id', $crId)
            ->where('active', '1')
            ->with('status')
            ->get();

        $this->info("Current active statuses: {$activeStatuses->count()}");
        foreach ($activeStatuses as $status) {
            $this->line("  - " . ($status->status ? $status->status->status_name : 'Unknown') . " (ID: {$status->new_status_id})");
        }

        // Run the function
        $service = new ChangeRequestStatusService();
        
        try {
            $this->line("\nExecuting handleNeedUpdateAction...");
            $result = $service->handleNeedUpdateAction($crId);
            
            $this->line("Function returned: " . ($result ? 'true' : 'false'));
            
            // Check results
            $afterStatuses = Change_request_statuse::where('cr_id', $crId)
                ->where('active', '1')
                ->with('status')
                ->get();

            $this->info("Active statuses after: {$afterStatuses->count()}");
            foreach ($afterStatuses as $status) {
                $this->line("  - " . ($status->status ? $status->status->status_name : 'Unknown') . " (ID: {$status->new_status_id})");
            }

            $this->info('✓ Test completed successfully');
            
        } catch (\Exception $e) {
            $this->error('✗ Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
