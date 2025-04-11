<?php

namespace Aaran\BMS\Billing\Master\Livewire\Class\Contact;

use Aaran\Assets\Traits\TenantAwareTrait;
use Aaran\BMS\Billing\Master\Models\ContactAddress;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class BillingAddress extends Component
{
    use TenantAwareTrait;

    public $search = '';
    public string $label = '';
    public $results = [];
    public $highlightIndex = 0;
    public $showDropdown = false;
    public $showCreateModal = false;

    public $initId;

    public function mount($initId = null): void
    {
        $this->initId = $initId;

        if ($initId && $this->getTenantConnection()) {

            $addressType = DB::connection($this->getTenantConnection())
                ->table('contact_addresses')
                ->where('contact_id', $initId)
                ->value('address_type');

            $this->search = $addressType ?: '';
        }
    }

    public function updatedSearch(): void
    {
        $this->searchBy();
    }

    public function searchBy(): void
    {
        if (!$this->getTenantConnection()) {
            return;
        }

        $query = DB::connection($this->getTenantConnection())
            ->table('contact_addresses')
            ->select(
                'contact_addresses.*',
                'cities.vname as city',
                'states.vname as state',
                'pincodes.vname as pincode',
                'countries.vname as country',
            )
            ->leftJoin('cities', 'cities.id', '=', 'contact_addresses.city_id')
            ->leftJoin('states', 'states.id', '=', 'contact_addresses.state_id')
            ->leftJoin('pincodes', 'pincodes.id', '=', 'contact_addresses.pincode_id')
            ->leftJoin('countries', 'countries.id', '=', 'contact_addresses.country_id')
            ->where('contact_addresses.contact_id', $this->initId)
            ->orderBy('contact_addresses.id');

        if (trim($this->search) !== '') {
            $query->where('address_type', 'like', '%' . $this->search . '%')->limit(10);
        }
        $this->results = $query->limit(10)->get();
        $this->highlightIndex = 0;
        $this->showDropdown = true;
    }

    public function incrementHighlight(): void
    {
        if ($this->highlightIndex < count($this->results) - 1) {
            $this->highlightIndex++;
        }
    }

    public function decrementHighlight(): void
    {
        if ($this->highlightIndex > 0) {
            $this->highlightIndex--;
        }
    }

    public function selectHighlighted(): void
    {
        $selected = $this->results[$this->highlightIndex] ?? null;
        if ($selected) {
            $this->selectContact($selected);
        }
    }

    public function selectContact($contactAddress): void
    {
        $contactAddress = (object)$contactAddress;

        $contactAddress = ContactAddress::on($this->getTenantConnection())
            ->where('id', $contactAddress->id)
            ->first();

        $v = $contactAddress->address_type .
            '  (' .
            $contactAddress->address_1 .', '.
            $contactAddress->address_2 .', '.
            $contactAddress->city->vname .', '.
            $contactAddress->state->vname .'- '.
            $contactAddress->pincode->vname .', '.
            $contactAddress->country->vname .'. '.
            ')';

        $this->search = $v;
        $this->dispatch('refresh-billing_address', $contactAddress->id);

        $this->results = [];
        $this->showDropdown = false;
        $this->showCreateModal = false;


    }

    public function hideDropdown(): void
    {
        $this->showDropdown = false;
    }

    public function openCreateModal(): void
    {
        $this->dispatch('open-create-address-modal', name: $this->initId);
        $this->showCreateModal = true;
    }

    #[On('refresh-address-lookup')]
    public function refreshProduct($contact): void
    {
        if (!empty($contact['id'])) {
            $this->initId = $contact['id'];

            $contactAddress = ContactAddress::on($this->getTenantConnection())
                ->where('contact_id', $this->initId)
                ->first();

            $v = $contactAddress->address_type .
                '  (' .
                $contactAddress->address_1 .', '.
                $contactAddress->address_2 .', '.
                $contactAddress->city->vname .', '.
                $contactAddress->state->vname .'- '.
                $contactAddress->pincode->vname .', '.
                $contactAddress->country->vname .'. '.
                ')';


            $this->search = $v;
        }
    }

    #[On('refresh-address-model')]
    public function refreshContact($contact): void
    {
        $this->search = $contact['address_type'] . $contact['address_1'] . $contact['address_2'];
        $this->showCreateModal = false;
    }


    public function render()
    {
        return view('master::contact.billing-address');
    }
}
