<?php

namespace Api\Modules\Integrations\Tally\Actions;

use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\AddChargeColumnsPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\AddMetaColumnsPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\AddMissingColumnsPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\AddOnlineChargesAndOtherLedgersPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\AddPaymentsInformationPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\AddTaxColumnsPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\DropAdditionalDataPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\EnrichLocationsWithExistingConfigurationPipe;
use Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport\GenerateLocationsPipe;
use Illuminate\Pipeline\Pipeline;


/**
 * Generate tally location configuration sheet
 */
class GenerateLocationConfigurationSheetAction
{
    /**
     * Generate location configuration sheet data considering need for blank configuration
     *
     * @param bool $needBlankConfig
     *
     * @return array
     */
    public function runWithPipeline(bool $needBlankConfig = false): array
    {
        $pipes = [
            GenerateLocationsPipe::class, // Generate locations
            AddMetaColumnsPipe::class, // Add meta columns to the locations
            AddTaxColumnsPipe::class, // Add all tax columns to the locations
            AddChargeColumnsPipe::class, // Add all charge columns to the locations
            AddOnlineChargesAndOtherLedgersPipe::class, // Add online charges and other ledgers to the locations
            AddPaymentsInformationPipe::class, // Add online charges and other ledgers to the locations
            DropAdditionalDataPipe::class, // Drop additional data from the locations. anything that is not a column in the sheet (starts with _)
            AddMissingColumnsPipe::class, // Add missing columns to the locations (Some taxes might not be present in the location, so we add them here with value "not applicable")
        ];

        if (! $needBlankConfig) {
            $pipes[] = EnrichLocationsWithExistingConfigurationPipe::class; // Enrich locations with existing configuration (if any)
        }

        $locations = app(Pipeline::class)
            ->through($pipes)
            ->thenReturn();

        return [
            'rows'   => $locations['locations'],
            'header' => $locations['columns'],
        ];
    }
}
