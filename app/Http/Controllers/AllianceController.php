<?php

namespace App\Http\Controllers;

use App\Models\Alliance;
use App\Models\AllianceCreationLog;
use App\Models\AllianceGoal;
use App\Models\AllianceMembership;
use App\Models\User;
use App\Models\UserResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AllianceController extends Controller
{
    private const CREATION_COOLDOWN_HOURS = 24;

    private const MEMBER_LIMIT_MIN = 2;

    private const MEMBER_LIMIT_MAX = 100;

    /**
     * @var list<string>
     */
    private const RESOURCE_TYPES = ['gold', 'wood', 'stone', 'food'];

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', Alliance::class);

        if ($this->userHasAlliance($user)) {
            return $this->backWithError('alliance', 'You are already in an alliance.');
        }

        if ($this->hasRecentAllianceCreation($user)) {
            return $this->backWithError('alliance', 'You can create only one alliance every 24 hours.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:80', 'unique:alliances,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_limit' => ['sometimes', 'integer', 'min:'.self::MEMBER_LIMIT_MIN, 'max:'.self::MEMBER_LIMIT_MAX],
            'is_open' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->userHasAlliance($lockedUser)) {
                throw ValidationException::withMessages([
                    'alliance' => 'You are already in an alliance.',
                ]);
            }

            if ($this->hasRecentAllianceCreation($lockedUser)) {
                throw ValidationException::withMessages([
                    'alliance' => 'You can create only one alliance every 24 hours.',
                ]);
            }

            $alliance = Alliance::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlugFor($validated['name']),
                'description' => $validated['description'] ?? null,
                'leader_id' => $lockedUser->id,
                'member_limit' => $validated['member_limit'] ?? 20,
                'is_open' => $validated['is_open'] ?? true,
            ]);

            AllianceMembership::create([
                'alliance_id' => $alliance->id,
                'user_id' => $lockedUser->id,
                'role' => 'leader',
                'joined_at' => now(),
            ]);

            AllianceCreationLog::create([
                'user_id' => $lockedUser->id,
                'created_at' => now(),
            ]);
        });

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function update(Request $request, Alliance $alliance): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:80', Rule::unique('alliances', 'name')->ignore($alliance->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'member_limit' => ['sometimes', 'integer', 'min:'.self::MEMBER_LIMIT_MIN, 'max:'.self::MEMBER_LIMIT_MAX],
            'is_open' => ['sometimes', 'boolean'],
        ]);

        $leaderOnlyFields = array_intersect(
            array_keys($validated),
            ['name', 'description', 'member_limit'],
        );

        if ($leaderOnlyFields !== []) {
            Gate::authorize('update', $alliance);
        } elseif (array_key_exists('is_open', $validated)) {
            Gate::authorize('updateVisibility', $alliance);
        }

        if (array_key_exists('name', $validated)) {
            $validated['slug'] = $this->uniqueSlugFor($validated['name'], $alliance);
        }

        $alliance->update($validated);

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function join(Request $request, Alliance $alliance): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('join', $alliance);

        if ($this->userHasAlliance($user)) {
            return $this->backWithError('alliance', 'You are already in an alliance.');
        }

        if (! $alliance->is_open) {
            return $this->backWithError('alliance', 'This alliance is invite-only.');
        }

        DB::transaction(function () use ($alliance, $user): void {
            $lockedAlliance = Alliance::query()
                ->whereKey($alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAlliance->memberships()->count() >= $lockedAlliance->member_limit) {
                throw ValidationException::withMessages([
                    'alliance' => 'This alliance is full.',
                ]);
            }

            AllianceMembership::create([
                'alliance_id' => $lockedAlliance->id,
                'user_id' => $user->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);
        });

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function leave(Request $request, Alliance $alliance): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('leave', $alliance);

        $membership = $user->allianceMembership()
            ->where('alliance_id', $alliance->id)
            ->first();

        if (! $membership) {
            return $this->backWithError('alliance', 'You are not a member of this alliance.');
        }

        if ($membership->role === 'leader') {
            return $this->backWithError('alliance', 'Transfer leadership or disband the alliance before leaving.');
        }

        $membership->delete();

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function destroy(Request $request, Alliance $alliance): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('delete', $alliance);

        $alliance->delete();

        return redirect()->to(url()->previous(route('dashboard')));
    }

    public function contribute(Request $request, AllianceGoal $goal): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('contribute', $goal);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'resource_type' => ['sometimes', 'string', Rule::in(self::RESOURCE_TYPES)],
        ]);

        if (($validated['resource_type'] ?? $goal->resource_type) !== $goal->resource_type) {
            return $this->backWithError('alliance_goal', 'This goal requires '.$goal->resource_type.'.');
        }

        if ($goal->status !== 'active') {
            return $this->backWithError('alliance_goal', 'This goal is not active.');
        }

        $membership = $user->allianceMembership()->firstOrFail();

        DB::transaction(function () use ($goal, $membership, $user, $validated): void {
            $amount = (int) $validated['amount'];
            $resourceType = $goal->resource_type;

            $resources = UserResource::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedGoal = AllianceGoal::query()
                ->whereKey($goal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedGoal->status !== 'active') {
                throw ValidationException::withMessages([
                    'alliance_goal' => 'This goal is not active.',
                ]);
            }

            if ($resources->{$resourceType} < $amount) {
                throw ValidationException::withMessages([
                    'alliance_goal' => 'Not enough '.$resourceType.' to contribute.',
                ]);
            }

            $resources->{$resourceType} -= $amount;
            $resources->save();

            $lockedGoal->contributions()->create([
                'user_id' => $user->id,
                'resource_type' => $resourceType,
                'amount' => $amount,
                'created_at' => now(),
            ]);

            $lockedGoal->current_amount = min(
                $lockedGoal->target_amount,
                $lockedGoal->current_amount + $amount,
            );

            if ($lockedGoal->current_amount >= $lockedGoal->target_amount) {
                $lockedGoal->status = 'completed';
                $lockedGoal->completed_at = now();
            }

            $lockedGoal->save();

            $membership->increment('total_contributed', $amount);
        });

        return redirect()->to(url()->previous(route('dashboard')));
    }

    private function userHasAlliance(User $user): bool
    {
        return $user->allianceMembership()->exists() || $user->ledAlliance()->exists();
    }

    private function hasRecentAllianceCreation(User $user): bool
    {
        return AllianceCreationLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(self::CREATION_COOLDOWN_HOURS))
            ->exists();
    }

    private function uniqueSlugFor(string $name, ?Alliance $ignoreAlliance = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while (Alliance::query()
            ->where('slug', $slug)
            ->when($ignoreAlliance, fn ($query) => $query->whereKeyNot($ignoreAlliance->id))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function backWithError(string $key, string $message): RedirectResponse
    {
        return redirect()
            ->to(url()->previous(route('dashboard')))
            ->withErrors([$key => $message]);
    }
}
