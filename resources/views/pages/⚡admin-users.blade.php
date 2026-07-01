<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')]
    #[Title('Users')]
    class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editingUserId = null;

    public ?int $deletingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $email_verified = true;

    public function mount(): void
    {
        abort_unless(
            config('app.admin_email') && auth()->user()?->email === config('app.admin_email'),
            403,
        );
    }

    public function updatedSearch(): void
    {
        $this->resetPage('usersPage');
    }

    public function createUser(): void
    {
        $this->resetForm();
        $this->email_verified = true;

        Flux::modal('user-form')->show();
    }

    public function editUser(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->email_verified = $user->email_verified_at !== null;

        $this->resetValidation();

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $isEditing = $this->editingUserId !== null;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->editingUserId),
            ],
            'password' => [
                $this->editingUserId ? 'nullable' : 'required',
                'string',
                Password::default(),
            ],
            'email_verified' => ['boolean'],
        ]);

        $user = $isEditing
            ? User::query()->findOrFail($this->editingUserId)
            : new User();

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $validated['email_verified'] ? now() : null,
        ]);

        if (filled($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        $this->resetForm();
        Flux::modal('user-form')->close();

        Flux::toast(variant: 'success', text: $isEditing ? __('User updated.') : __('User created.'));
    }

    public function confirmDelete(int $userId): void
    {
        if ($userId === Auth::id()) {
            Flux::toast(variant: 'warning', text: __('You cannot delete your own account here.'));

            return;
        }

        $this->deletingUserId = $userId;

        Flux::modal('delete-user')->show();
    }

    public function deleteUser(): void
    {
        if (! $this->deletingUserId || $this->deletingUserId === Auth::id()) {
            return;
        }

        User::query()->findOrFail($this->deletingUserId)->delete();

        $this->deletingUserId = null;

        Flux::modal('delete-user')->close();
        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        Flux::modal('user-form')->close();
    }

    public function cancelDelete(): void
    {
        $this->deletingUserId = null;
        Flux::modal('delete-user')->close();
    }

    public function with(): array
    {
        $search = trim($this->search);

        return [
            'users' => User::query()
                ->withCount([
                    'linkTrackers',
                    'linkRotators',
                    'banners',
                    'bannerRotators',
                ])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->simplePaginate(10, pageName: 'usersPage'),
        ];
    }

    private function resetForm(): void
    {
        $this->reset('editingUserId', 'name', 'email', 'password');
        $this->email_verified = true;
        $this->resetValidation();
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('Create, edit, and remove user accounts.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="createUser">
            {{ __('New User') }}
        </flux:button>
    </div>

    <flux:card>
        <div class="space-y-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :label="__('Search users')"
                type="search"
                autocomplete="off"
                placeholder="email@example.com"
                class="max-w-md" />

            <flux:table :paginate="$users">
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Link') }}</flux:table.column>
                    <flux:table.column>{{ __('Banner') }}</flux:table.column>
                    <flux:table.column>{{ __('Verified') }}</flux:table.column>
                    <flux:table.column>{{ __('Created') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                #{{ $user->id }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium">{{ $user->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm">
                                    {{ __(':count trackers', ['count' => number_format($user->link_trackers_count)]) }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __(':count rotators', ['count' => number_format($user->link_rotators_count)]) }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm">
                                    {{ __(':count trackers', ['count' => number_format($user->banners_count)]) }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __(':count rotators', ['count' => number_format($user->banner_rotators_count)]) }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $user->email_verified_at ? __('Yes') : __('No') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $user->created_at?->format('M j, Y') }}
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" type="button" wire:click="editUser({{ $user->id }})" :aria-label="__('Edit')" />
                                    <flux:button variant="ghost" size="sm" icon="trash" type="button" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" wire:click="confirmDelete({{ $user->id }})" :aria-label="__('Delete')" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No users found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <flux:modal name="user-form" class="md:w-[32rem]" @close="cancelForm">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">
                    {{ $editingUserId ? __('Edit User') : __('New User') }}
                </flux:heading>
                <flux:text>{{ __('Manage account details and access status.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input
                wire:model="password"
                :label="$editingUserId ? __('New password') : __('Password')"
                type="password"
                autocomplete="new-password"
                :placeholder="$editingUserId ? __('Leave blank to keep current password') : __('Password')"
                :required="! $editingUserId"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable />

            <flux:checkbox wire:model="email_verified" :label="__('Email verified')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="filled" type="button" wire:click="cancelForm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-user" class="md:w-[28rem]" @close="cancelDelete">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete User') }}</flux:heading>
                <flux:text>{{ __('This user and related data will be deleted permanently.') }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="filled" type="button" wire:click="cancelDelete">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" type="button" wire:click="deleteUser">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
