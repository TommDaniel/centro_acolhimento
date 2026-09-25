<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use LogicException;

class PasswordResetTokenService
{
    public function consumeLocked(User $user, string $token): bool
    {
        $this->assertInsideTransaction();

        $email = $user->getEmailForPasswordReset();
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->lockForUpdate()
            ->first();

        if ($record === null || ! Password::broker()->tokenExists($user, $token)) {
            return false;
        }

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return true;
    }

    public function revokeLocked(User $user): void
    {
        $this->assertInsideTransaction();

        $emails = array_values(array_unique(array_filter([
            $user->getEmailForPasswordReset(),
            $user->getRawOriginal('email'),
        ], fn (mixed $email): bool => is_string($email) && $email !== '')));

        DB::table('password_reset_tokens')
            ->whereIn('email', $emails)
            ->lockForUpdate()
            ->delete();
    }

    private function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Password reset tokens must be changed inside the user lock transaction.');
        }
    }
}
