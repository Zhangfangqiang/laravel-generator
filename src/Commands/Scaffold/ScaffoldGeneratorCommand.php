<?php

namespace ZhangFang\Generator\Commands\Scaffold;

use ZhangFang\Generator\Commands\BaseCommand;
use ZhangFang\Generator\Common\CommandData;
use ZhangFang\Generator\Generators\API\APIControllerGenerator;
use ZhangFang\Generator\Generators\API\APIRequestGenerator;

class ScaffoldGeneratorCommand extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'infyom:scaffold';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a full CRUD views for given model';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->commandData = new CommandData($this, CommandData::$COMMAND_TYPE_SCAFFOLD);
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        parent::handle();

        //ZhangFang\Generator\Common\GeneratorField 是这里的内容也不知道干什么用的
        if ($this->checkIsThereAnyDataToGenerate()) {
            #生成公用项 migration model factory seeder
            $this->generateCommonItems();
            #生成脚手架 requests controllers views routes menu
            $this->generateScaffoldItems();
            #生成api
            $controllerGenerator = new APIControllerGenerator($this->commandData);
            $controllerGenerator->generate();
            #生成api_request
            $requestGenerator = new APIRequestGenerator($this->commandData);
            $requestGenerator->generate();
            #创建resource
            $modelName = $this->commandData->modelName;
            \Artisan::call("make:resource Admin/$modelName" . "Resources");
            #使用迁移执行后期操作
            $this->performPostActionsWithMigration();
        } else {
            $this->commandData->commandInfo('There are not enough input fields for scaffold generation.');
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    public function getOptions()
    {
        return array_merge(parent::getOptions(), []);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array_merge(parent::getArguments(), []);
    }

    /**
     * Check if there is anything to generate.
     *
     * @return bool
     */
    protected function checkIsThereAnyDataToGenerate()
    {
        if (count($this->commandData->fields) > 1) {
            return true;
        }
    }
}
