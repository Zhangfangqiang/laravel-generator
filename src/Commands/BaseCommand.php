<?php

namespace ZhangFang\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ZhangFang\Generator\Common\CommandData;
use ZhangFang\Generator\Generators\API\APIControllerGenerator;
use ZhangFang\Generator\Generators\API\APIRequestGenerator;
use ZhangFang\Generator\Generators\API\APIRoutesGenerator;
use ZhangFang\Generator\Generators\API\APITestGenerator;
use ZhangFang\Generator\Generators\FactoryGenerator;
use ZhangFang\Generator\Generators\MigrationGenerator;
use ZhangFang\Generator\Generators\ModelGenerator;
use ZhangFang\Generator\Generators\RepositoryTestGenerator;
use ZhangFang\Generator\Generators\Scaffold\ControllerGenerator;
use ZhangFang\Generator\Generators\Scaffold\RequestGenerator;
use ZhangFang\Generator\Generators\Scaffold\RoutesGenerator;
use ZhangFang\Generator\Generators\Scaffold\ViewGenerator;
use ZhangFang\Generator\Generators\SeederGenerator;
use ZhangFang\Generator\Utils\FileUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class BaseCommand extends Command
{
    /**
     * The command Data.
     *
     * @var CommandData
     */
    public $commandData;

    /**
     * @var Composer
     */
    public $composer;

    /**
     * 构造方法
     * BaseCommand constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->composer = app()['composer'];
    }

    public function handle()
    {
        $this->commandData->modelName = $this->argument('model');               #获得命令中的模型参数
        $this->commandData->initCommandData();                                       #来自控制台的输入 可能是
        $this->commandData->getFields();                                             #来自文件的输入   可能是
    }

    /**
     * migration model factory seeder
     * 生成公用项目
     */
    public function generateCommonItems()
    {
        if (!$this->commandData->getOption('fromTable') and !$this->isSkip('migration')) {
            $migrationGenerator = new MigrationGenerator($this->commandData);
            $migrationGenerator->generate();
        }

        if (!$this->isSkip('model')) {
            $modelGenerator = new ModelGenerator($this->commandData);
            $modelGenerator->generate();
        }

        if ($this->commandData->getOption('factory') || (!$this->isSkip('tests') and $this->commandData->getAddOn('tests'))) {
            $factoryGenerator = new FactoryGenerator($this->commandData);
            $factoryGenerator->generate();
        }

        if ($this->commandData->getOption('seeder')) {
            $seederGenerator = new SeederGenerator($this->commandData);
            $seederGenerator->generate();
            $seederGenerator->updateMainSeeder();
        }
    }

    /**
     * 这个是api的
     */
    public function generateAPIItems()
    {
        if (!$this->isSkip('requests') and !$this->isSkip('api_requests')) {
            $requestGenerator = new APIRequestGenerator($this->commandData);
            $requestGenerator->generate();
        }

        if (!$this->isSkip('controllers') and !$this->isSkip('api_controller')) {
            $controllerGenerator = new APIControllerGenerator($this->commandData);
            $controllerGenerator->generate();
        }

        if (!$this->isSkip('routes') and !$this->isSkip('api_routes')) {
            $routesGenerator = new APIRoutesGenerator($this->commandData);
            $routesGenerator->generate();
        }

        if (!$this->isSkip('tests') and $this->commandData->getAddOn('tests')) {
            if ($this->commandData->getOption('repositoryPattern')) {
                $repositoryTestGenerator = new RepositoryTestGenerator($this->commandData);
                $repositoryTestGenerator->generate();
            }

            $apiTestGenerator = new APITestGenerator($this->commandData);
            $apiTestGenerator->generate();
        }
    }

    /**
     * #生成脚手架 requests controllers views routes
     */
    public function generateScaffoldItems()
    {
        if (!$this->isSkip('requests') and !$this->isSkip('scaffold_requests')) {
            $requestGenerator = new RequestGenerator($this->commandData);
            $requestGenerator->generate();
        }

        if (!$this->isSkip('controllers') and !$this->isSkip('scaffold_controller')) {
            $controllerGenerator = new ControllerGenerator($this->commandData);
            $controllerGenerator->generate();
        }

        if (!$this->isSkip('views')) {
            $viewGenerator = new ViewGenerator($this->commandData);
            $viewGenerator->generate();
        }

        if (!$this->isSkip('routes') and !$this->isSkip('scaffold_routes')) {
            $routeGenerator = new RoutesGenerator($this->commandData);
            $routeGenerator->generate();
        }
    }

    /**
     * 执行后动作
     * @param bool $runMigration
     */
    public function performPostActions($runMigration = false)
    {
        if ($this->commandData->getOption('save')) {
            $this->saveSchemaFile();
        }

        if ($runMigration) {
            if ($this->commandData->getOption('forceMigrate')) {
                $this->runMigration();
            } elseif (!$this->commandData->getOption('fromTable') and !$this->isSkip('migration')) {
                $requestFromConsole = (php_sapi_name() == 'cli') ? true : false;
                if ($this->commandData->getOption('jsonFromGUI') && $requestFromConsole) {
                    $this->runMigration();
                } elseif ($requestFromConsole && $this->confirm("\nDo you want to migrate database? [y|N]", false)) {
                    $this->runMigration();
                }
            }
        }

        if ($this->commandData->getOption('localized')) {
            $this->saveLocaleFile();
        }

        if (!$this->isSkip('dump-autoload')) {
            $this->info('Generating autoload files');
            $this->composer->dumpOptimized();
        }
    }

    /**
     * 运行迁移
     * @return bool
     */
    public function runMigration()
    {
        $migrationPath = config('infyom.laravel_generator.path.migration', database_path('migrations/'));
        $path = Str::after($migrationPath, base_path()); // get path after base_path
        $this->call('migrate', ['--path' => $path, '--force' => true]);

        return true;
    }

    /**
     * 判断这个关键词是否存在
     * @param $skip
     * @return bool
     */
    public function isSkip($skip)
    {
        if ($this->commandData->getOption('skip')) {
            return in_array($skip, (array)$this->commandData->getOption('skip'));
        }

        return false;
    }

    /**
     * 执行迁移后的操作
     */
    public function performPostActionsWithMigration()
    {
        $this->performPostActions(true);
    }

    /**
     *保存方案文件
     */
    private function saveSchemaFile()
    {
        $fileFields = [];

        foreach ($this->commandData->fields as $field) {
            $fileFields[] = [
                'name' => $field->name,
                'dbType' => $field->dbInput,
                'htmlType' => $field->htmlInput,
                'validations' => $field->validations,
                'searchable' => $field->isSearchable,
                'fillable' => $field->isFillable,
                'primary' => $field->isPrimary,
                'inForm' => $field->inForm,
                'inIndex' => $field->inIndex,
                'inView' => $field->inView,
            ];
        }

        foreach ($this->commandData->relations as $relation) {
            $fileFields[] = [
                'type' => 'relation',
                'relation' => $relation->type . ',' . implode(',', $relation->inputs),
            ];
        }

        $path = config('infyom.laravel_generator.path.schema_files', resource_path('model_schemas/'));

        $fileName = $this->commandData->modelName . '.json';

        if (file_exists($path . $fileName) && !$this->confirmOverwrite($fileName)) {
            return;
        }
        FileUtil::createFile($path, $fileName, json_encode($fileFields, JSON_PRETTY_PRINT));
        $this->commandData->commandComment("\nSchema File saved: ");
        $this->commandData->commandInfo($fileName);
    }

    /**
     * 保存区域设置文件
     */
    private function saveLocaleFile()
    {
        $locales = [
            'singular' => $this->commandData->modelName,
            'plural' => $this->commandData->config->mPlural,
            'fields' => [],
        ];

        foreach ($this->commandData->fields as $field) {
            $locales['fields'][$field->name] = Str::title(str_replace('_', ' ', $field->name));
        }

        $path = config('infyom.laravel_generator.path.models_locale_files', base_path('resources/lang/en/models/'));

        $fileName = $this->commandData->config->mCamelPlural . '.php';

        if (file_exists($path . $fileName) && !$this->confirmOverwrite($fileName)) {
            return;
        }
        $content = "<?php\n\nreturn " . var_export($locales, true) . ';' . \PHP_EOL;
        FileUtil::createFile($path, $fileName, $content);
        $this->commandData->commandComment("\nModel Locale File saved: ");
        $this->commandData->commandInfo($fileName);
    }

    /**
     * 确认覆盖提示
     * @param $fileName
     * @param string $prompt
     * @return bool
     */
    protected function confirmOverwrite($fileName, $prompt = '')
    {
        $prompt = (empty($prompt))
            ? $fileName . ' already exists. Do you want to overwrite it? [y|N]'
            : $prompt;

        return $this->confirm($prompt, false);
    }

    /**
     * 获取选项
     * @return array
     */
    public function getOptions()
    {
        return [
            ['fieldsFile', null, InputOption::VALUE_REQUIRED, 'Fields input as json file'],
            ['jsonFromGUI', null, InputOption::VALUE_REQUIRED, 'Direct Json string while using GUI interface'],
            ['plural', null, InputOption::VALUE_REQUIRED, 'Plural Model name'],
            ['tableName', null, InputOption::VALUE_REQUIRED, 'Table Name'],
            ['fromTable', null, InputOption::VALUE_NONE, 'Generate from existing table'],
            ['ignoreFields', null, InputOption::VALUE_REQUIRED, 'Ignore fields while generating from table'],
            ['save', null, InputOption::VALUE_NONE, 'Save model schema to file'],
            ['primary', null, InputOption::VALUE_REQUIRED, 'Custom primary key'],
            ['prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for all files'],
            ['paginate', null, InputOption::VALUE_REQUIRED, 'Pagination for index.blade.php'],
            ['skip', null, InputOption::VALUE_REQUIRED, 'Skip Specific Items to Generate (migration,model,controllers,api_controller,scaffold_controller,repository,requests,api_requests,scaffold_requests,routes,api_routes,scaffold_routes,views,tests,menu,dump-autoload)'],
            ['datatables', null, InputOption::VALUE_REQUIRED, 'Override datatables settings'],
            ['views', null, InputOption::VALUE_REQUIRED, 'Specify only the views you want generated: index,create,edit,show'],
            ['relations', null, InputOption::VALUE_NONE, 'Specify if you want to pass relationships for fields'],
            ['softDelete', null, InputOption::VALUE_NONE, 'Soft Delete Option'],
            ['forceMigrate', null, InputOption::VALUE_NONE, 'Specify if you want to run migration or not'],
            ['factory', null, InputOption::VALUE_NONE, 'To generate factory'],
            ['seeder', null, InputOption::VALUE_NONE, 'To generate seeder'],
            ['localized', null, InputOption::VALUE_NONE, 'Localize files.'],
            ['repositoryPattern', null, InputOption::VALUE_REQUIRED, 'Repository Pattern'],
            ['connection', null, InputOption::VALUE_REQUIRED, 'Specify connection name'],
        ];
    }

    /**
     *获取控制台命令参数。
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['model', InputArgument::REQUIRED, 'Singular Model name'],
        ];
    }
}
