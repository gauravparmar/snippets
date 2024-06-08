<?php

namespace Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport;

use Closure;


class DropAdditionalDataPipe extends BaseLocationConfigurationExportPipe
{
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
        $this->columns = $data['columns'];
        $locations     = array_map(function ($location) {
            unset($location['_additional']);

            return $location;
        }, $data['locations']);

        return $next([
            'locations' => $locations,
            'columns'   => $this->columns,
        ]);
    }
}
