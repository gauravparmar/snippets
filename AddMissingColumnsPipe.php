<?php

namespace Api\Modules\Integrations\Tally\Pipes\LocationConfigurationExport;

use Closure;


class AddMissingColumnsPipe extends BaseLocationConfigurationExportPipe
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
        $locations     = $data['locations'];
        $this->columns = $data['columns'];

        foreach ($this->columns as $column) {
            foreach ($locations as $key => $value) {
                if (! array_key_exists($column, $value)) {
                    $locations[$key][$column] = 'Not Applicable';
                }
            }
        }

        return $next([
            'locations' => $locations,
            'columns'   => $this->columns,
        ]);
    }
}
