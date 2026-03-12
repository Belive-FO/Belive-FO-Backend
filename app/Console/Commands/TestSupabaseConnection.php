<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Saeedvir\Supabase\Facades\Supabase;
use Exception;

class TestSupabaseConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supabase:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Supabase connection and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Supabase Connection...');
        $this->newLine();

        // Check configuration
        $this->info('📋 Configuration Check:');
        $url = config('supabase.url');
        $key = config('supabase.key');
        $secret = config('supabase.secret');
        $jwtSecret = config('supabase.jwt_secret');

        $configStatus = true;

        if (empty($url) || $url === 'https://xxxxx.supabase.co') {
            $this->error('  ❌ SUPABASE_URL is not configured');
            $configStatus = false;
        } else {
            $this->line('  ✅ SUPABASE_URL: ' . $url);
        }

        if (empty($key) || $key === 'your-anon-key') {
            $this->error('  ❌ SUPABASE_KEY is not configured');
            $configStatus = false;
        } else {
            $this->line('  ✅ SUPABASE_KEY: ' . substr($key, 0, 20) . '...');
        }

        if (empty($secret) || $secret === 'your-service-role-key') {
            $this->error('  ❌ SUPABASE_SECRET is not configured');
            $configStatus = false;
        } else {
            $this->line('  ✅ SUPABASE_SECRET: ' . substr($secret, 0, 20) . '...');
        }

        if (empty($jwtSecret) || $jwtSecret === 'your-super-secret-jwt-secret-at-least-32-characters') {
            $this->error('  ❌ SUPABASE_JWT_SECRET is not configured');
            $configStatus = false;
        } else {
            $this->line('  ✅ SUPABASE_JWT_SECRET: ' . substr($jwtSecret, 0, 20) . '...');
        }

        if (!$configStatus) {
            $this->newLine();
            $this->error('Please configure all Supabase settings in your .env file.');
            $this->info('See .env.example for instructions on how to get these values.');
            return Command::FAILURE;
        }

        $this->newLine();

        // Test connection by making a simple API call
        $this->info('🔌 Connection Test:');
        try {
            // Get Supabase service info
            $info = Supabase::info();
            
            $this->line('  ✅ Supabase service initialized');
            $this->line('  📍 URL: ' . $info['url']);
            $this->line('  🔗 Connected: ' . ($info['connected'] ? 'Yes' : 'No'));
            
            $this->newLine();
            $this->line('  📦 Available Services:');
            foreach ($info['services'] as $service => $enabled) {
                $status = $enabled ? '✅' : '❌';
                $this->line("    {$status} " . ucfirst($service));
            }

            // Try to make a simple API request to verify connectivity
            $this->newLine();
            $this->info('🌐 API Connectivity Test:');
            
            // Access the service instance to get the client
            $service = app('supabase');
            $client = $service->getClient();
            $response = $client->request('GET', '/rest/v1/', [
                'headers' => [
                    'apikey' => config('supabase.key'),
                ],
            ]);

            if (isset($response['error'])) {
                $this->warn('  ⚠️  API request returned an error (this may be normal):');
                $this->line('     Status: ' . ($response['status'] ?? 'Unknown'));
                $this->line('     Message: ' . ($response['message'] ?? 'No message'));
            } else {
                $this->line('  ✅ Successfully connected to Supabase API');
            }

            $this->newLine();
            $this->info('✅ Supabase connection test completed successfully!');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->newLine();
            $this->error('❌ Connection test failed:');
            $this->error('   ' . $e->getMessage());
            $this->newLine();
            $this->warn('Please check:');
            $this->line('  1. Your internet connection');
            $this->line('  2. Your Supabase project is active');
            $this->line('  3. Your SUPABASE_URL is correct');
            $this->line('  4. Your SUPABASE_KEY is valid');
            
            return Command::FAILURE;
        }
    }
}

