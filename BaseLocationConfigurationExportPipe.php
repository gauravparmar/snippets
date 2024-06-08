<?php

namespace Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport;

use Api\Core\Helpers\Helpers;
use Api\Modules\User\Definitions\SettingDefinition;
use Closure;
use Exception;
use Illuminate\Support\Collection;


abstract class BaseLocationConfigurationExportPipe
{
    /** @var Collection $locationConfiguration */
    protected Collection $locationConfiguration;

    /** @var array $columns */
    protected array $columns = [];

    /** @var bool $useSeparateLedgerForEachLocation */
    protected bool $useSeparateLedgerForEachLocation = false;

    /** @var bool $useSeparateLedgerForOnlineAndPos */
    protected bool $useSeparateLedgerForOnlineAndPos = false;

    /** @var bool $useSeparateLedgerForEachBrand */
    protected bool $useSeparateLedgerForEachBrand = false;

    /** @var bool $useSeparateLedgerForEachPlatform */
    protected bool $useSeparateLedgerForEachPlatform = false;

    /** @var bool $useSeparateLedgerForEachFloor */
    protected bool $useSeparateLedgerForEachFloor = false;

    /** @var bool $usePaymentsDataForTally */
    protected bool $usePaymentsDataForTally = false;

    /** @var bool $useTransferReceiveDataForTally */
    protected bool $useTransferReceiveDataForTally = false;


    /**
     * BaseLocationConfigurationExportPipe constructor
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->addDefaultColumns();
        $this->initializeQuestions();
    }

    /**
     * Initialize the default columns
     *
     * @return void
     */
    protected function addDefaultColumns(): void
    {
        $this->columns = [
            'Location ID',
            'Location Name',
            'Online/In-Store',
            'Brand ID',
            'Brand Name',
            'Floor ID',
            'Floor Name',
            'Platform ID',
            'Platform Name',
        ];
    }

    /**
     * Initialize questions
     *
     * @return void
     * @throws Exception
     */
    private function initializeQuestions(): void
    {
        $company = Helpers::getCurrentCompany();

        $questionnaire = $company->getTallyIntegration()->getConfigArray('questionnaire');

        if (empty($questionnaire)) {
            throw new Exception('Tally: Questionnaire is not set. Cannot generate locations configuration.');
        }

        $questions = collect($questionnaire)
            ->map(function ($item) {
                return [
                    'question' => $item['question'],
                    // convert answer to boolean if it is a string yes or no
                    'answer'   => is_string($item['answer']) && in_array(strtolower($item['answer']), ['yes', 'no'])
                        ? strtolower($item['answer']) === 'yes'
                        : $item['answer'],
                ];
            })
            ->mapWithKeys(function ($item) {
                return [$item['question'] => $item['answer']];
            })->toArray();

        $this->useSeparateLedgerForEachLocation = $questions['ledger_location'] ?? false;
        $this->useSeparateLedgerForOnlineAndPos = $questions['ledger_location_type'] ?? false;
        $this->useSeparateLedgerForEachBrand    = $questions['ledger_brand'] ?? false;
        $this->useSeparateLedgerForEachPlatform = $questions['ledger_platform'] ?? false;
        $this->useSeparateLedgerForEachFloor    = $questions['ledger_floor'] ?? false;
        $this->usePaymentsDataForTally          = $questions['export_payments'] ?? false;
        $this->useTransferReceiveDataForTally   = $questions['export_transfer_receives'] ?? false;
    }

    /**
     * Handle the pipe.
     *
     * @param mixed   $data
     * @param Closure $next
     *
     * @return mixed
     */
    abstract public function handle(mixed $data, Closure $next): mixed;

    /**
     * Merge new columns with existing columns
     *
     * @param array $columns
     *
     * @return void
     */
    public function addNewColumns(array $columns): void
    {
        // Check for duplicate columns and add only unique columns
        $this->columns = array_unique(array_merge($this->columns, $columns));
    }

    /**
     *  Hydrate locations with existing configuration
     *
     * @param $location
     *
     * @return array
     * @throws Exception
     */
    public function getExistingLocation($location): array
    {
        $company = Helpers::getCurrentCompany();

        // If company doesn't have a tally location configuration file, return empty
        if (empty($company->getSetting(SettingDefinition::TALLY_LOCATION_CONFIGURATION_FILE_ID))) {
            return [];
        }

        if (empty($this->locationConfiguration)) {
            $this->locationConfiguration = $company->getTallyLocationConfiguration();
        }

        $query = $this->locationConfiguration->where('Location ID', $location['Location ID']);

        if ($this->useSeparateLedgerForOnlineAndPos) {
            $query = $query->where('Online/In-Store', $location['Online/In-Store']);
        }

        if ($this->useSeparateLedgerForEachBrand) {
            $query = $query->where('Brand ID', $location['Brand ID']);
        }

        if ($this->useSeparateLedgerForEachPlatform) {
            $query = $query->where('Platform ID', $location['Platform ID']);
        }

        if ($this->useSeparateLedgerForEachFloor) {
            $query = $query->where('Floor ID', $location['Floor ID']);
        }

        return $query->first() ?? [];
    }

    /**
     * Get the default meta columns that need to be filled by the merchant
     *
     * @return array
     */
    protected function getMetaColumns(): array
    {
        return [
            'Cost Center'   => '',
            'Godown'        => '',
            'Voucher Type'  => '',
            'Sales Account' => '',
            'Party Ledger'  => '',
        ];
    }
}
