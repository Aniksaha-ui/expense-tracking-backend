<?php

namespace App\Console\Commands;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Console\Command;

class CheckGoogleVisionCredentials extends Command
{
    protected $signature = 'google:vision-check';
    protected $description = 'Verify the Google Vision service-account configuration';

    public function handle(): int
    {
        $project = trim((string) config('services.transaction_ocr.google_vision.project_id'));
        $path = trim((string) config('services.transaction_ocr.google_vision.credentials'));

        if ($project === '' || $path === '' || ! is_readable($path)) {
            $this->error('Google Vision is not ready. Check GOOGLE_CLOUD_PROJECT and the absolute GOOGLE_APPLICATION_CREDENTIALS path.');
            return self::FAILURE;
        }

        $credentials = json_decode((string) file_get_contents($path), true);
        if (! is_array($credentials) || ($credentials['type'] ?? '') !== 'service_account') {
            $this->error('The credentials file is not a valid service-account JSON file.');
            return self::FAILURE;
        }

        try {
            $token = (new ServiceAccountCredentials(['https://www.googleapis.com/auth/cloud-vision'], $credentials))->fetchAuthToken();
            if (empty($token['access_token'])) {
                throw new \RuntimeException('No access token was returned.');
            }
        } catch (\Throwable $exception) {
            $this->error('Google authentication failed: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Google Vision credentials are valid for project {$project}.");
        return self::SUCCESS;
    }
}
