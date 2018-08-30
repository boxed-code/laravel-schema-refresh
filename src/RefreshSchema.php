<?php

namespace BoxedCode\Laravel\SchemaRefresh;

use Artisan;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

class RefreshSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refreshes the DB with the latest schema. (SQLite only)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(DatabaseManager $db, Repository $config)
    {
        $connectionName = $config->get('database.default');
        $defaultConfig = $config->get('database.connections')[$connectionName];

        // Error out if we're not SQLite.
        if ('sqlite' !== $defaultConfig['driver']) {
            throw new Exception(
                sprintf('This command can only operate on SQLite databases, your current default connection is %s.', $defaultConfig['driver'])
            );
        }

        // Create a temporary database.
        $path = database_path('fresh.sqlite');
        $this->line('Creating new database '. $path);
        @unlink($path);
        touch($path);

        // Create the temporary configuration.
        $freshConfig = [
            "driver" => "sqlite",
            "database" => $path,
            "prefix" => ""
        ];

        $config->set('database.connections.fresh', $freshConfig);

        // Apply our migrations to the new DB.
        $this->line('Applying migrations to new DB'.PHP_EOL);

        $exitCode = Artisan::call('migrate', [
            '--database' => 'fresh',
        ]);

        if (0 !== $exitCode) {
            throw new Exception('Failed to apply migrations on the new database.');
        }

        // Switch back to the master DB.
        $db->setDefaultConnection('sqlite');

        // Get a table list from the current DB.
        $tables = $db->select('SELECT name FROM sqlite_master WHERE type=\'table\';');
        $this->line('There are '.count($tables).' tables to transfer.'.PHP_EOL);

        foreach ($tables as $table) {
            if ($table->name !== 'migrations') {

                // Get the row and column counts.
                $this->line('Inspecting '.$table->name.'...');
                $columns = $db->getSchemaBuilder()->getColumnListing($table->name);
                $new_columns =  $db->connection('fresh')->getSchemaBuilder()->getColumnListing($table->name);
                $rows = $db->query()->from($table->name)->count();
                $this->line($rows.' rows with '.count($columns).' columns.');

                $bar = $this->output->createProgressBar($rows);

                // Copy the rows from one to the other.
                $query = $db->query()->from($table->name)->orderBy('id', 'ASC');
                $query->chunk(1000, function($results) use ($db, $table, $bar, $columns, $new_columns) {
                    foreach ($results as $row) {
                        $row = (array) $row;
                        $db->connection('fresh')->query()->from($table->name)->insert(array_only($row, $new_columns));
                        $bar->advance();
                    }
                });

                $bar->finish();

                $this->line(PHP_EOL);
            }
        }

        $this->info('Swapping current database for fresh database.');
        rename($defaultConfig['database'], $defaultConfig['database'] . '.old');
        rename($freshConfig['database'], $defaultConfig['database']);

        $this->info('Command complete.');
    }
}
