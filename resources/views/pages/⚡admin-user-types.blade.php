<?php

use App\Models\UserType;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')]
    #[Title('User Types')]
    class extends Component
{
    use WithPagination;

    public ?int $editingUserTypeId = null;

    public ?int $deletingUserTypeId = null;

    public string $label = '';

    public string $max_link_trackers = '';

    public string $max_link_rotators = '';

    public string $max_banner_trackers = '';

    public string $max_banner_rotators = '';

    public string $sortField = 'id';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['id', 'label', 'max_link_trackers', 'max_link_rotators', 'max_banner_trackers', 'max_banner_rotators', 'created_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'label' ? 'asc' : 'desc';
        }

        $this->resetPage('userTypesPage');
    }

    public function createUserType(): void
    {
        $this->resetForm();

        Flux::modal('user-type-form')->show();
    }

    public function editUserType(int $userTypeId): void
    {
        $userType = UserType::query()->findOrFail($userTypeId);

        $this->editingUserTypeId = $userType->id;
        $this->label = $userType->label;
        $this->max_link_trackers = $this->limitToInput($userType->max_link_trackers);
        $this->max_link_rotators = $this->limitToInput($userType->max_link_rotators);
        $this->max_banner_trackers = $this->limitToInput($userType->max_banner_trackers);
        $this->max_banner_rotators = $this->limitToInput($userType->max_banner_rotators);

        $this->resetValidation();

        Flux::modal('user-type-form')->show();
    }

    public function save(): void
    {
        $isEditing = $this->editingUserTypeId !== null;

        $validated = $this->validate([
            'label' => ['required', 'string', 'max:100', Rule::unique(UserType::class)->ignore($this->editingUserTypeId)],
            'max_link_trackers' => ['nullable', 'integer', 'min:0'],
            'max_link_rotators' => ['nullable', 'integer', 'min:0'],
            'max_banner_trackers' => ['nullable', 'integer', 'min:0'],
            'max_banner_rotators' => ['nullable', 'integer', 'min:0'],
        ]);

        $userType = $isEditing
            ? UserType::query()->findOrFail($this->editingUserTypeId)
            : new UserType();

        $userType->forceFill([
            'label' => $validated['label'],
            'max_link_trackers' => $this->emptyToNull($validated['max_link_trackers']),
            'max_link_rotators' => $this->emptyToNull($validated['max_link_rotators']),
            'max_banner_trackers' => $this->emptyToNull($validated['max_banner_trackers']),
            'max_banner_rotators' => $this->emptyToNull($validated['max_banner_rotators']),
        ])->save();

        $this->resetForm();
        Flux::modal('user-type-form')->close();

        Flux::toast(variant: 'success', text: $isEditing ? __('User type updated.') : __('User type created.'));
    }

    public function confirmDelete(int $userTypeId): void
    {
        $this->deletingUserTypeId = $userTypeId;

        Flux::modal('delete-user-type')->show();
    }

    public function deleteUserType(): void
    {
        if (! $this->deletingUserTypeId) {
            return;
        }

        $userType = UserType::query()
            ->withCount('users')
            ->findOrFail($this->deletingUserTypeId);

        if ($userType->users_count > 0) {
            Flux::modal('delete-user-type')->close();
            Flux::toast(variant: 'warning', text: __('This user type is assigned to users and cannot be deleted.'));

            $this->deletingUserTypeId = null;

            return;
        }

        $userType->delete();

        $this->deletingUserTypeId = null;

        Flux::modal('delete-user-type')->close();
        Flux::toast(variant: 'success', text: __('User type deleted.'));
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        Flux::modal('user-type-form')->close();
    }

    public function cancelDelete(): void
    {
        $this->deletingUserTypeId = null;
        Flux::modal('delete-user-type')->close();
    }

    public function with(): array
    {
        return [
            'userTypes' => UserType::query()
                ->withCount('users')
                ->orderBy($this->sortField, $this->sortDirection)
                ->simplePaginate(10, pageName: 'userTypesPage'),
        ];
    }

    private function resetForm(): void
    {
        $this->reset(
            'editingUserTypeId',
            'label',
            'max_link_trackers',
            'max_link_rotators',
            'max_banner_trackers',
            'max_banner_rotators',
        );

        $this->resetValidation();
    }

    private function limitToInput(?int $limit): string
    {
        return $limit === null ? '' : (string) $limit;
    }

    private function emptyToNull(mixed $value): ?int
    {
        return $value === '' || $value === null ? null : (int) $value;
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl">{{ __('User Types') }}</flux:heading>
            <flux:subheading>{{ __('Manage membership types and usage limits.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="createUserType">
            {{ __('New User Type') }}
        </flux:button>
    </div>

    <flux:card>
        <div class="relative">
            <div
                wire:loading.flex
                wire:target="sortBy,previousPage,nextPage,gotoPage"
                class="absolute inset-0 z-10 items-center justify-center rounded-md bg-white/75 backdrop-blur-sm dark:bg-zinc-950/75">
                <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <flux:icon.arrow-path class="size-4 animate-spin" />
                    <span>{{ __('Loading user types...') }}</span>
                </div>
            </div>

            <flux:table :paginate="$userTypes">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'id'" :direction="$sortDirection" wire:click="sortBy('id')" class="cursor-pointer">{{ __('ID') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'label'" :direction="$sortDirection" wire:click="sortBy('label')" class="cursor-pointer">{{ __('Label') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'max_link_trackers'" :direction="$sortDirection" wire:click="sortBy('max_link_trackers')" class="cursor-pointer">{{ __('Link Trackers') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'max_link_rotators'" :direction="$sortDirection" wire:click="sortBy('max_link_rotators')" class="cursor-pointer">{{ __('Link Rotators') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'max_banner_trackers'" :direction="$sortDirection" wire:click="sortBy('max_banner_trackers')" class="cursor-pointer">{{ __('Banner Trackers') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'max_banner_rotators'" :direction="$sortDirection" wire:click="sortBy('max_banner_rotators')" class="cursor-pointer">{{ __('Banner Rotators') }}</flux:table.column>
                    <flux:table.column>{{ __('Users') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($userTypes as $userType)
                        <flux:table.row :key="$userType->id">
                            <flux:table.cell class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                #{{ $userType->id }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium">{{ $userType->label }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $userType->max_link_trackers === null ? __('Unlimited') : number_format($userType->max_link_trackers) }}</flux:table.cell>
                            <flux:table.cell>{{ $userType->max_link_rotators === null ? __('Unlimited') : number_format($userType->max_link_rotators) }}</flux:table.cell>
                            <flux:table.cell>{{ $userType->max_banner_trackers === null ? __('Unlimited') : number_format($userType->max_banner_trackers) }}</flux:table.cell>
                            <flux:table.cell>{{ $userType->max_banner_rotators === null ? __('Unlimited') : number_format($userType->max_banner_rotators) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($userType->users_count) }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-start gap-1">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" type="button" wire:click="editUserType({{ $userType->id }})" :aria-label="__('Edit')" />
                                    <flux:button variant="ghost" size="sm" icon="trash" type="button" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" wire:click="confirmDelete({{ $userType->id }})" :aria-label="__('Delete')" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No user types found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <flux:modal name="user-type-form" class="md:w-[36rem]" @close="cancelForm">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">
                    {{ $editingUserTypeId ? __('Edit User Type') : __('New User Type') }}
                </flux:heading>
                <flux:text>{{ __('Leave a limit blank for unlimited usage.') }}</flux:text>
            </div>

            <flux:input wire:model="label" :label="__('Label')" placeholder="Premium" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="max_link_trackers" :label="__('Max Link Trackers')" type="number" min="0" placeholder="Unlimited" />
                <flux:input wire:model="max_link_rotators" :label="__('Max Link Rotators')" type="number" min="0" placeholder="Unlimited" />
                <flux:input wire:model="max_banner_trackers" :label="__('Max Banner Trackers')" type="number" min="0" placeholder="Unlimited" />
                <flux:input wire:model="max_banner_rotators" :label="__('Max Banner Rotators')" type="number" min="0" placeholder="Unlimited" />
            </div>

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

    <flux:modal name="delete-user-type" class="md:w-[28rem]" @close="cancelDelete">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete User Type') }}</flux:heading>
                <flux:text>{{ __('This user type can only be deleted when no users are assigned to it.') }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="filled" type="button" wire:click="cancelDelete">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" type="button" wire:click="deleteUserType">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
