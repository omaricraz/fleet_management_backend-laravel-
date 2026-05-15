<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverAccountProvisioningService
{
    private const DRIVER_EMAIL_DOMAIN = 'driver.local';

    private const DRIVER_DEFAULT_PASSWORD = '12345678';

    /**
     * @param  array{full_name: string, phone: string, zone_id?: int|null}  $data
     * @return array{user: User, driver: Driver}
     */
    public function createDriverWithAccount(int $tenantId, array $data): array
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $fullName = $data['full_name'];
            $email = $this->generateUniqueDriverEmail($fullName);

            $user = User::withoutEvents(function () use ($fullName, $email, $tenantId): User {
                return User::query()->create([
                    'name' => $fullName,
                    'email' => $email,
                    'password' => self::DRIVER_DEFAULT_PASSWORD,
                    'tenant_id' => $tenantId,
                    'role' => 'driver',
                ]);
            });

            $driver = Driver::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'full_name' => $fullName,
                'phone' => $data['phone'],
                'zone_id' => $data['zone_id'] ?? null,
            ]);

            return [
                'user' => $user->fresh(['driver']),
                'driver' => $driver->loadMissing('zone'),
            ];
        });
    }

    protected function generateUniqueDriverEmail(string $fullName): string
    {
        $base = strtolower(Str::slug($fullName, '-') ?: 'driver');
        $base = substr($base, 0, 150);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $suffix = (string) random_int(100, 999999);
            $email = "{$base}-{$suffix}@".self::DRIVER_EMAIL_DOMAIN;

            if (! User::query()->where('email', $email)->exists()) {
                return $email;
            }
        }

        return strtolower("{$base}-".Str::uuid().'@'.self::DRIVER_EMAIL_DOMAIN);
    }
}
