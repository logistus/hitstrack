<?php

use App\Models\User;
use App\Models\UserType;
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

    public string $emailFilterInput = '';

    public string $nameFilterInput = '';

    public string $userTypeFilterInput = '';

    public string $verifiedFilterInput = '';

    public string $createdFilterInput = '';

    public string $emailFilter = '';

    public string $nameFilter = '';

    public string $userTypeFilter = '';

    public string $verifiedFilter = '';

    public string $createdFilter = '';

    public ?int $editingUserId = null;

    public ?int $deletingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $email_verified = true;

    public string $user_type_id = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function applyFilters(): void
    {
        $this->emailFilter = trim($this->emailFilterInput);
        $this->nameFilter = trim($this->nameFilterInput);
        $this->userTypeFilter = $this->userTypeFilterInput;
        $this->verifiedFilter = $this->verifiedFilterInput;
        $this->createdFilter = $this->createdFilterInput;

        $this->resetPage('usersPage');
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['id', 'name', 'email', 'user_type_id', 'email_verified_at', 'created_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['name', 'email'], true) ? 'asc' : 'desc';
        }

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
        $this->user_type_id = (string) $user->user_type_id;

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
            'user_type_id' => ['required', Rule::exists('user_types', 'id')],
        ]);

        $user = $isEditing
            ? User::query()->findOrFail($this->editingUserId)
            : new User();

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'user_type_id' => $validated['user_type_id'],
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
        $emailFilter = trim($this->emailFilter);
        $nameFilter = trim($this->nameFilter);

        return [
            'users' => User::query()
                ->with('userType')
                ->when($emailFilter !== '', fn ($query) => $query->where('email', 'like', "%{$emailFilter}%"))
                ->when($nameFilter !== '', fn ($query) => $query->where('name', 'like', "%{$nameFilter}%"))
                ->when($this->userTypeFilter !== '', fn ($query) => $query->where('user_type_id', $this->userTypeFilter))
                ->when($this->verifiedFilter === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
                ->when($this->verifiedFilter === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
                ->when($this->createdFilter !== '', fn ($query) => $query->whereDate('created_at', $this->createdFilter))
                ->orderBy($this->sortField, $this->sortDirection)
                ->simplePaginate(10, pageName: 'usersPage'),
            'userTypes' => UserType::query()->orderBy('id')->get(),
        ];
    }

    private function resetForm(): void
    {
        $this->reset('editingUserId', 'name', 'email', 'password');
        $this->email_verified = true;
        $this->user_type_id = (string) UserType::query()->where('label', 'Free')->value('id');
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
            <div class="space-y-3">
                <flux:heading size="md">{{ __('Filter by') }}</flux:heading>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_1fr_10rem_10rem_10rem_auto]">
                    <flux:input
                        wire:model.defer="emailFilterInput"
                        :label="__('By Email')"
                        type="search"
                        autocomplete="off"
                        placeholder="email@example.com" />

                    <flux:input
                        wire:model.defer="nameFilterInput"
                        :label="__('By Name')"
                        type="search"
                        autocomplete="off"
                        placeholder="Jane Doe" />

                    <flux:select wire:model.defer="userTypeFilterInput" :label="__('By User Type')">
                        <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                        @foreach ($userTypes as $userType)
                            <flux:select.option value="{{ $userType->id }}">{{ $userType->label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.defer="verifiedFilterInput" :label="__('By Verified')">
                        <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                        <flux:select.option value="verified">{{ __('Verified') }}</flux:select.option>
                        <flux:select.option value="unverified">{{ __('Unverified') }}</flux:select.option>
                    </flux:select>

                    <flux:input
                        wire:model.defer="createdFilterInput"
                        :label="__('By Created')"
                        type="date" />

                    <div class="flex items-end">
                        <flux:button variant="primary" icon="funnel" wire:click="applyFilters" class="w-full">
                            {{ __('Filter') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div
                    wire:loading.flex
                    wire:target="applyFilters,previousPage,nextPage,gotoPage"
                    class="absolute inset-0 z-10 items-center justify-center rounded-md bg-white/75 backdrop-blur-sm dark:bg-zinc-950/75">
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        <span>{{ __('Loading users...') }}</span>
                    </div>
                </div>

                <flux:table :paginate="$users">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortField === 'id'" :direction="$sortDirection" wire:click="sortBy('id')" class="cursor-pointer">{{ __('ID') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')" class="cursor-pointer">{{ __('Name') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'email'" :direction="$sortDirection" wire:click="sortBy('email')" class="cursor-pointer">{{ __('Email') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'user_type_id'" :direction="$sortDirection" wire:click="sortBy('user_type_id')" class="cursor-pointer">{{ __('User Type') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'email_verified_at'" :direction="$sortDirection" wire:click="sortBy('email_verified_at')" class="cursor-pointer">{{ __('Verified') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'created_at'" :direction="$sortDirection" wire:click="sortBy('created_at')" class="cursor-pointer">{{ __('Created') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
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
                                    {{ $user->userType?->label ?? __('Free') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $user->email_verified_at ? __('Yes') : __('No') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $user->created_at?->format('M j, Y') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-start gap-1">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" type="button" wire:click="editUser({{ $user->id }})" :aria-label="__('Edit')" />
                                        <flux:button variant="ghost" size="sm" icon="trash" type="button" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" wire:click="confirmDelete({{ $user->id }})" :aria-label="__('Delete')" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    {{ __('No users found.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
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
            <flux:select wire:model="user_type_id" :label="__('User Type')">
                @foreach ($userTypes as $userType)
                    <flux:select.option value="{{ $userType->id }}">{{ $userType->label }}</flux:select.option>
                @endforeach
            </flux:select>
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
