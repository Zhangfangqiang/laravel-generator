<?php

namespace ZhangFang\Generator\Common;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use ZhangFang\Generator\Utils\TableFieldsGenerator;
use ZhangFang\Generator\Utils\GeneratorFieldsInputUtil;

class CommandData
{
    public static $COMMAND_TYPE_API          = 'api';
    public static $COMMAND_TYPE_SCAFFOLD     = 'scaffold';
    public static $COMMAND_TYPE_API_SCAFFOLD = 'api_scaffold';
    public static $COMMAND_TYPE_VUEJS        = 'vuejs';

    /** @var string */
    public $modelName;
    public $commandType;

    /** @var GeneratorConfig */
    public $config;

    /** @var GeneratorField[] */
    public $fields = [];

    /** @var GeneratorFieldRelation[] */
    public $relations = [];

    /** @var Command */
    public $commandObj;

    /** @var TemplatesManager */
    private $templateManager;

    /** @var array */
    public $dynamicVars = [];
    public $fieldNamesMapping = [];

    /** @var CommandData */
    protected static $instance = null;

    /**
     * 获取实例
     * @return CommandData
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * 获取模板管理器
     * @return TemplatesManager
     */
    public function getTemplatesManager()
    {
        return $this->templateManager;
    }

    /**
     * 是本地化模板
     * @return bool
     */
    public function isLocalizedTemplates()
    {
        return $this->templateManager->isUsingLocale();
    }

    /**
     * 构造方法
     * CommandData constructor.
     * @param Command $commandObj
     * @param $commandType
     * @param TemplatesManager|null $templatesManager
     */
    public function __construct(Command $commandObj, $commandType, TemplatesManager $templatesManager = null)
    {
        $this->commandObj = $commandObj;

        if (is_null($templatesManager)) {
            $this->templateManager = app(TemplatesManager::class);
        } else {
            $this->templateManager = $templatesManager;
        }

        $this->commandType = $commandType;

        $this->fieldNamesMapping = [
            '$FIELD_NAME_TITLE$' => 'fieldTitle',
            '$FIELD_NAME$'       => 'name',
        ];

        $this->config = new GeneratorConfig();
    }

    /**
     * 命令错误
     * @param $error
     */
    public function commandError($error)
    {
        $this->commandObj->error($error);
    }

    /**
     * 命令注释
     * @param $message
     */
    public function commandComment($message)
    {
        $this->commandObj->comment($message);
    }

    /**
     * 命令警告
     * @param $warning
     */
    public function commandWarn($warning)
    {
        $this->commandObj->warn($warning);
    }

    /**
     * 命令信息
     * @param $message
     */
    public function commandInfo($message)
    {
        $this->commandObj->info($message);
    }

    /**
     * 初始化命令数据
     */
    public function initCommandData()
    {
        $this->config->init($this);
    }

    /**
     * 获得选项
     * @param $option
     * @return bool
     */
    public function getOption($option)
    {
        return $this->config->getOption($option);
    }

    /**
     * 获取附件
     * @param $option
     * @return bool
     */
    public function getAddOn($option)
    {
        return $this->config->getAddOn($option);
    }

    /**
     * 设置选项
     * @param $option
     * @param $value
     */
    public function setOption($option, $value)
    {
        $this->config->setOption($option, $value);
    }

    /**
     * 添加动态变量
     * @param $name
     * @param $val
     */
    public function addDynamicVariable($name, $val)
    {
        $this->dynamicVars[$name] = $val;
    }

    /**
     * 获得字段
     */
    public function getFields()
    {
        $this->fields = [];

        if ($this->getOption('fieldsFile') or $this->getOption('jsonFromGUI')) {
            $this->getInputFromFileOrJson();
        } elseif ($this->getOption('fromTable')) {
            $this->getInputFromTable();
        } else {
            $this->getInputFromConsole();
        }
    }

    /**
     *从控制台获取输入
     */
    private function getInputFromConsole()
    {
        $this->commandInfo('Specify fields for the model (skip id & timestamp fields, we will add it automatically)');
        $this->commandInfo('Read docs carefully to specify field inputs)');
        $this->commandInfo('Enter "exit" to finish');

        $this->addPrimaryKey();

        while (true) {
            $fieldInputStr = $this->commandObj->ask('Field: (name db_type html_type options)', '');

            if (empty($fieldInputStr) || $fieldInputStr == false || $fieldInputStr == 'exit') {
                break;
            }

            if (!GeneratorFieldsInputUtil::validateFieldInput($fieldInputStr)) {
                $this->commandError('Invalid Input. Try again');
                continue;
            }

            $validations = $this->commandObj->ask('Enter validations: ', false);
            $validations = ($validations == false) ? '' : $validations;

            if ($this->getOption('relations')) {
                $relation = $this->commandObj->ask('Enter relationship (Leave Blank to skip):', false);
            } else {
                $relation = '';
            }

            $this->fields[] = GeneratorFieldsInputUtil::processFieldInput(
                $fieldInputStr,
                $validations
            );

            if (!empty($relation)) {
                $this->relations[] = GeneratorFieldRelation::parseRelation($relation);
            }
        }

        if (config('infyom.laravel_generator.timestamps.enabled', true)) {
            $this->addTimestamps();
        }
    }

    /**
     * 添加主键
     */
    private function addPrimaryKey()
    {
        $primaryKey = new GeneratorField();
        if ($this->getOption('primary')) {
            $primaryKey->name = $this->getOption('primary');
        } else {
            $primaryKey->name = 'id';
        }
        $primaryKey->parseDBType('increments');
        $primaryKey->parseOptions('s,f,p,if,ii');

        $this->fields[] = $primaryKey;
    }

    /**
     * 添加时标
     */
    private function addTimestamps()
    {
        $createdAt = new GeneratorField();
        $createdAt->name = 'created_at';
        $createdAt->parseDBType('timestamp');
        $createdAt->parseOptions('s,f,if,ii');
        $this->fields[] = $createdAt;

        $updatedAt = new GeneratorField();
        $updatedAt->name = 'updated_at';
        $updatedAt->parseDBType('timestamp');
        $updatedAt->parseOptions('s,f,if,ii');
        $this->fields[] = $updatedAt;
    }

    /**
     *从文件或Json获取输入
     */
    private function getInputFromFileOrJson()
    {
        // fieldsFile option will get high priority than json option if both options are passed
        try {
            if ($this->getOption('fieldsFile')) {
                $fieldsFileValue = $this->getOption('fieldsFile');
                if (file_exists($fieldsFileValue)) {
                    $filePath = $fieldsFileValue;
                } elseif (file_exists(base_path($fieldsFileValue))) {
                    $filePath = base_path($fieldsFileValue);
                } else {
                    $schemaFileDirector = config(
                        'infyom.laravel_generator.path.schema_files',
                        resource_path('model_schemas/')
                    );
                    $filePath = $schemaFileDirector.$fieldsFileValue;
                }

                if (!file_exists($filePath)) {
                    $this->commandError('Fields file not found');
                    exit;
                }

                $fileContents = file_get_contents($filePath);
                $jsonData = json_decode($fileContents, true);
                $this->fields = [];
                foreach ($jsonData as $field) {
                    if (isset($field['type']) && $field['relation']) {
                        $this->relations[] = GeneratorFieldRelation::parseRelation($field['relation']);
                    } else {
                        $this->fields[] = GeneratorField::parseFieldFromFile($field);
                        if (isset($field['relation'])) {
                            $this->relations[] = GeneratorFieldRelation::parseRelation($field['relation']);
                        }
                    }
                }
            } else {
                $fileContents = $this->getOption('jsonFromGUI');
                $jsonData = json_decode($fileContents, true);

                // override config options from jsonFromGUI
                $this->config->overrideOptionsFromJsonFile($jsonData);

                // Manage custom table name option
                if (isset($jsonData['tableName'])) {
                    $tableName = $jsonData['tableName'];
                    $this->config->tableName = $tableName;
                    $this->addDynamicVariable('$TABLE_NAME$', $tableName);
                    $this->addDynamicVariable('$TABLE_NAME_TITLE$', Str::studly($tableName));
                }

                // Manage migrate option
                if (isset($jsonData['migrate']) && $jsonData['migrate'] == false) {
                    $this->config->options['skip'][] = 'migration';
                }

                foreach ($jsonData['fields'] as $field) {
                    if (isset($field['type']) && $field['relation']) {
                        $this->relations[] = GeneratorFieldRelation::parseRelation($field['relation']);
                    } else {
                        $this->fields[] = GeneratorField::parseFieldFromFile($field);
                        if (isset($field['relation'])) {
                            $this->relations[] = GeneratorFieldRelation::parseRelation($field['relation']);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->commandError($e->getMessage());
            exit;
        }
    }

    /**
     *从表获取输入
     */
    private function getInputFromTable()
    {
        $tableName = $this->dynamicVars['$TABLE_NAME$'];

        $ignoredFields = $this->getOption('ignoreFields');
        if (!empty($ignoredFields)) {
            $ignoredFields = explode(',', trim($ignoredFields));
        } else {
            $ignoredFields = [];
        }

        $tableFieldsGenerator = new TableFieldsGenerator($tableName, $ignoredFields, $this->config->connection);
        $tableFieldsGenerator->prepareFieldsFromTable();
        $tableFieldsGenerator->prepareRelations();

        $this->fields = $tableFieldsGenerator->fields;
        $this->relations = $tableFieldsGenerator->relations;
    }
}
