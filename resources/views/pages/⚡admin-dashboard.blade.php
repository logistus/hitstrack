<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')]
    #[Title('Admin Dashboard')]
    class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
};
?>

<section class="container mx-auto space-y-8">
    <div class="space-y-2">
        <flux:heading size="xl">{{ __('Admin Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Admin tools will live here.') }}</flux:subheading>
    </div>
</section>
