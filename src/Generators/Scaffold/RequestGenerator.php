<?php

namespace ZhangFang\Generator\Generators\Scaffold;

use ZhangFang\Generator\Common\CommandData;
use ZhangFang\Generator\Generators\BaseGenerator;
use ZhangFang\Generator\Generators\ModelGenerator;
use ZhangFang\Generator\Utils\FileUtil;

class RequestGenerator extends BaseGenerator
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
        $this->commandData = $commandData;
        $this->path        = $commandData->config->pathRequest;

        $this->fileName       = $this->commandData->modelName.'Request.php';
    }

    public function generate()
    {
        $this->generateRequest();
    }

    private function generateRequest()
    {
        if (is_file($this->path . $this->fileName)) {
            return;
        }
        $templateData = get_template('scaffold.request.request', 'laravel-generator');
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, $this->fileName, $templateData);

        $this->commandData->commandComment("\n Request created: ");
        $this->commandData->commandInfo($this->fileName);
    }

    public function rollback()
    {
        if ($this->rollbackFile($this->path, $this->fileName)) {
            $this->commandData->commandComment(' API Request file deleted: '.$this->fileName);
        }
    }
}
