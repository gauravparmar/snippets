<?php

namespace Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport;

use Api\Core\Helpers\Helpers;
use Api\Modules\Location\Models\Location;
use Api\Modules\Location\Models\RestaurantFloor;
use Api\Modules\Location\Services\LocationService;
use Api\Modules\Platform\Models\Platform;
use Closure;


class GenerateLocationsPipe extends BaseLocationConfigurationExportPipe
{
    /** @var array $locations */
    private array $locations = [];

    /**
     * Handle the pipe.
     *
     * @param mixed   $data
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(mixed $data, Closure $next): mixed
    {
        $this->generateLocations();

        $this->removeColumnsBasedOnQuestions();

        return $next([
            'locations' => $this->locations,
            'columns'   => $this->columns,
        ]);
    }

    /**
     * Generate locations array
     *
     * @return void
     */
    private function generateLocations(): void
    {
        if (! $this->useSeparateLedgerForEachLocation) {
            $this->addCompanyAsLocation();
            return;
        }

        $onlineLocations = app(LocationService::class)->getByType(Location::TYPE_ONLINE);
        $onlineLocations->loadMissing(['platforms']);

        $posLocations = app(LocationService::class)->getByType(Location::TYPE_POS);
        $posLocations->loadMissing('floors');

        foreach ($onlineLocations as $onlineLocation) {
            // Add location without platform
            if (! $this->useSeparateLedgerForOnlineAndPos) {
                $this->addOnlineLocation($onlineLocation);
                continue;
            }

            // Add locations with platforms
            if ($this->useSeparateLedgerForEachPlatform) {
                $locationPlatforms = $onlineLocation->platforms;

                foreach ($locationPlatforms as $locationPlatform) {
                    $this->addOnlineLocation($onlineLocation, $locationPlatform->platform);
                }
            } else {
                $this->addOnlineLocation($onlineLocation);
            }
        }

        foreach ($posLocations as $posLocation) {
            // Add location without floor
            if (! $this->useSeparateLedgerForOnlineAndPos) {
                $this->addPosLocation($posLocation);
                continue;
            }

            // Add locations with floors
            if ($this->useSeparateLedgerForEachFloor) {
                $locationFloors = $posLocation->floors;

                foreach ($locationFloors as $locationFloor) {
                    $this->addPosLocation($posLocation, $locationFloor);
                }
            } else {
                $this->addPosLocation($posLocation);
            }
        }
    }

    /**
     * Add company name as location
     *
     * @return void
     */
    private function addCompanyAsLocation(): void
    {
        $na                = 'Not Applicable';
        $company           = Helpers::getCurrentCompany();
        $this->locations[] = [
            'Location ID'     => $company->id,
            'Location Name'   => $company->name,
            'Online/In-Store' => $na,
            'Brand ID'        => $na,
            'Brand Name'      => $na,
            'Floor ID'        => $na,
            'Floor Name'      => $na,
            'Platform ID'     => $na,
            'Platform Name'   => $na,
            '_additional'     => [
                'type' => 'company',
            ],
        ];
    }

    /**
     * Add online location to locations array
     *
     * @param Location      $onlineLocation
     * @param Platform|null $platform
     *
     * @return void
     */
    private function addOnlineLocation(Location $onlineLocation, Platform $platform = null): void
    {
        $na                = 'Not Applicable';
        $this->locations[] = [
            'Location ID'     => $onlineLocation->id,
            'Location Name'   => $onlineLocation->physical_location?->name ?? $onlineLocation->name,
            'Online/In-Store' => $this->getFormattedLocationType($onlineLocation->type),
            'Brand ID'        => $onlineLocation->kitchen_brand?->id ?? '-',
            'Brand Name'      => $onlineLocation->kitchen_brand?->name ?? '-',
            'Floor ID'        => $na,
            'Floor Name'      => $na,
            'Platform ID'     => $platform?->id ?? '-',
            'Platform Name'   => $platform?->name ?? '-',
            '_additional'     => [
                'type'     => 'location',
                'location' => $onlineLocation,
                'platform' => $platform,
            ],
        ];
    }

    /**
     * Get formatted location type
     *
     * @param string $type
     *
     * @return string
     */
    private function getFormattedLocationType(string $type): string
    {
        return match ($type) {
            Location::TYPE_ONLINE => 'Online',
            Location::TYPE_POS => 'In-Store',
        };
    }

    /**
     * Add POS location to locations array
     *
     * @param Location             $posLocation
     * @param RestaurantFloor|null $floor
     *
     * @return void
     */
    public function addPosLocation(Location $posLocation, RestaurantFloor $floor = null): void
    {
        $na                = 'Not Applicable';
        $this->locations[] = [
            'Location ID'     => $posLocation->id,
            'Location Name'   => $posLocation->physical_location?->name ?? $posLocation->name,
            'Online/In-Store' => $this->getFormattedLocationType($posLocation->type),
            'Brand ID'        => $na,
            'Brand Name'      => $na,
            'Floor ID'        => $floor?->id ?? '-',
            'Floor Name'      => $floor?->name ?? '-',
            'Platform ID'     => $na,
            'Platform Name'   => $na,
            '_additional'     => [
                'type'     => 'location',
                'location' => $posLocation,
                'floor'    => $floor,
            ],
        ];
    }

    /**
     * Remove columns based on questions
     *
     * @return void
     */
    public function removeColumnsBasedOnQuestions(): void
    {
        if (! $this->useSeparateLedgerForEachLocation) {
            $this->columns = array_diff($this->columns, ['Online/In-Store', 'Brand ID', 'Brand Name', 'Floor ID', 'Floor Name', 'Platform ID', 'Platform Name']);
        }

        if (! $this->useSeparateLedgerForOnlineAndPos) {
            $this->columns = array_diff($this->columns, ['Online/In-Store']);
        }

        if (! $this->useSeparateLedgerForEachBrand) {
            $this->columns = array_diff($this->columns, ['Brand ID', 'Brand Name']);
        }

        if (! $this->useSeparateLedgerForEachPlatform) {
            $this->columns = array_diff($this->columns, ['Platform ID', 'Platform Name']);
        }

        if (! $this->useSeparateLedgerForEachFloor) {
            $this->columns = array_diff($this->columns, ['Floor ID', 'Floor Name']);
        }
    }
}
