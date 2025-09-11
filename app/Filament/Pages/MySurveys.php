<?php

namespace App\Filament\Pages;

use App\Models\SurveyUser;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class MySurveys extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Pengaturan Akun';
    protected static ?string $title = 'Survei Saya';
    protected static string $view = 'filament.pages.my-surveys';

    public string $search = '';
    public string $sortBy = 'surveys.start_date';
    public string $sortDir = 'desc';
    public int $perPage = 10;
    public ?string $status = null;
    public ?int $year = null;

    protected $queryString = ['search', 'sortBy', 'sortDir', 'perPage', 'status', 'year'];
    protected $updatesQueryString = true;

    // Penting: reset halaman saat filter berubah
    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function updatingStatus()
    {
        $this->resetPage();
    }
    public function updatingYear()
    {
        $this->resetPage();
    }
    public function updatingSortBy()
    {
        $this->resetPage();
    }
    public function updatingSortDir()
    {
        $this->resetPage();
    }
    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function getRowsProperty()
    {
        $query = SurveyUser::query()
            ->with(['survey'])
            ->where('user_id', Auth::id())
            ->join('surveys', 'surveys.id', '=', 'survey_users.survey_id')
            ->select([
                'survey_users.*',
                'surveys.start_date',
                'surveys.end_date',
                'surveys.name',
            ]);

        // Cari langsung di tabel yang sudah di-join
        if ($this->search !== '') {
            $query->where('surveys.name', 'like', "%{$this->search}%");
        }

        if ($this->status) {
            $query->where('survey_users.status', $this->status);
        }

        if ($this->year) {
            $query->whereYear('surveys.start_date', $this->year);
        }

        // Safety: hanya izinkan kolom sort yang valid
        $sortable = ['surveys.start_date', 'surveys.end_date', 'surveys.name'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'surveys.start_date';
        $sortDir = strtolower($this->sortDir) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($this->perPage);
    }
}
