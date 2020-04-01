<?php

namespace ZhangFang\Generator\Generators\API;

use ZhangFang\Generator\Common\CommandData;
use ZhangFang\Generator\Generators\BaseGenerator;
use ZhangFang\Generator\Generators\ModelGenerator;
use ZhangFang\Generator\Utils\FileUtil;

class APIRequestGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;

    /** @var string */
    private $fileName;

    /** @var string */
    private $createFileName;

    /** @var string */
    private $updateFileName;

    public function __construct(CommandData $commandData)
    {
        $this->commandData    = $commandData;
        $this->path           = $commandData->config->pathApiRequest;
        $this->fileName       = $this->commandData->modelName . 'ApiRequest.php';
        $this->createFileName = 'Create' . $this->fileName;
        $this->updateFileName = 'Update' . $this->fileName;
    }

    public function generate()
    {
        $this->generateRequest();
    }

    private function generateRequest()
    {
        $templateData = get_template('api.request.request', 'laravel-generator');

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, $this->fileName, $templateData);

        $this->commandData->commandComment("\nCreate Request created: ");
        $this->commandData->commandInfo($this->fileName);
    }

    private function generateCreateRequest()
    {
        $templateData = get_template('api.request.create_request', 'laravel-generator');

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, $this->createFileName, $templateData);

        $this->commandData->commandComment("\nCreate Request created: ");
        $this->commandData->commandInfo($this->createFileName);
    }

    private function generateUpdateRequest()
    {
        $modelGenerator = new ModelGenerator($this->commandData);
        $rules = $modelGenerator->generateUniqueRules();
        $this->commandData->addDynamicVariable('$UNIQUE_RULES$', $rules);

        $templateData = get_template('api.request.update_request', 'laravel-generator');

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, $this->updateFileName, $templateData);

        $this->commandData->commandComment("\nUpdate Request created: ");
        $this->commandData->commandInfo($this->updateFileName);
    }

    public function rollback()
    {
        if ($this->rollbackFile($this->path, $this->createFileName)) {
            $this->commandData->commandComment('Create API Request file deleted: '.$this->createFileName);
        }

        if ($this->rollbackFile($this->path, $this->updateFileName)) {
            $this->commandData->commandComment('Update API Request file deleted: '.$this->updateFileName);
        }
    }
}
