<?php

namespace ZhangFang\Generator\Generators\Scaffold;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ZhangFang\Generator\Common\CommandData;
use ZhangFang\Generator\Generators\BaseGenerator;
use ZhangFang\Generator\Generators\ViewServiceProviderGenerator;
use ZhangFang\Generator\Utils\FileUtil;
use ZhangFang\Generator\Utils\HTMLFieldGenerator;

class ViewGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;

    /** @var string */
    private $templateType;

    /** @var array */
    private $htmlFields;

    public function __construct(CommandData $commandData)
    {
        $this->commandData  = $commandData;
        $this->path         = $commandData->config->pathViews;
        $this->templateType = 'laravel-generator';

    }

    public function generate()
    {
        if (!file_exists($this->path)) {
            mkdir($this->path, 0755, true);
        }

        $htmlInputs = Arr::pluck($this->commandData->fields, 'htmlInput');
        if (in_array('file', $htmlInputs)) {
            $this->commandData->addDynamicVariable('$FILES$', ", 'files' => true");
        }

        $this->commandData->commandComment("\nGenerating Views...");

        if ($this->commandData->getOption('views')) {
            $viewsToBeGenerated = explode(',', $this->commandData->getOption('views'));

            if (in_array('create', $viewsToBeGenerated)) {
                $this->generateCreate();
            }

            if (in_array('edit', $viewsToBeGenerated)) {
                $this->generateUpdate();
            }

        } else {
            $this->generateIndex();
            $this->generateCreate();
            $this->generateUpdate();
        }

        $this->commandData->commandComment('Views created: ');
    }

    /**
     * 创建首页
     */
    private function generateIndex()
    {
        $templateName = 'index';

        if ($this->commandData->isLocalizedTemplates()) {
            $templateName .= '_locale';
        }

        $templateData = get_template('scaffold.views.'.$templateName, $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        if ($this->commandData->getAddOn('datatables')) {
            $templateData = str_replace('$PAGINATE$', '', $templateData);
        } else {
            $paginate = $this->commandData->getOption('paginate');

            if ($paginate) {
                $paginateTemplate = get_template('scaffold.views.paginate', $this->templateType);

                $paginateTemplate = fill_template($this->commandData->dynamicVars, $paginateTemplate);

                $templateData = str_replace('$PAGINATE$', $paginateTemplate, $templateData);
            } else {
                $templateData = str_replace('$PAGINATE$', '', $templateData);
            }
        }

        $zfField    = '';
        $zfSearchForm = '';

        foreach ($this->commandData->fields as $field) {
            $name          = $field->name;

            if (!strstr($name, '_at')) {
                $zfSearchForm .= "<div class='layui-inline'>
                                <label class='layui-form-label'>$name</label>
                                <div class='layui-input-inline'>
                                    <input type='text' name='$name' placeholder='请输入' autocomplete='off' class='layui-input'>
                                </div>
                            </div>";
            }

            $zfField      .= "
                              {field: \"$name\"    , title: \"$name\"},";
        }

        $templateData = str_replace('$ZF_SEARCH_FORM$', $zfSearchForm, $templateData);
        $templateData = str_replace('$ZF_FIELD$', $zfField, $templateData);
        FileUtil::createFile($this->path, 'index.blade.php', $templateData);
        $this->commandData->commandInfo('index.blade.php created');
    }

    /**
     * 视图 composer
     * @param $tableName
     * @param $variableName
     * @param $columns
     * @param $selectTable
     * @return string|string[]
     */
    private function generateViewComposer($tableName, $variableName, $columns, $selectTable)
    {
        $fieldTemplate = get_template('scaffold.fields.select', $this->templateType);

        $viewServiceProvider = new ViewServiceProviderGenerator($this->commandData);
        $viewServiceProvider->generate();
        $viewServiceProvider->addViewVariables($tableName.'.fields', $variableName, $columns, $selectTable);

        $fieldTemplate = str_replace(
            '$INPUT_ARR$',
            '$'.$variableName,
            $fieldTemplate
        );

        return $fieldTemplate;
    }

    /**
     * 创建字段
     */
    private function generateFields()
    {
        $templateName = 'fields';
        $localized    = false;

        if ($this->commandData->isLocalizedTemplates()) {
            $templateName .= '_locale';
            $localized = true;
        }

        $this->htmlFields = [];

        foreach ($this->commandData->fields as $field) {
            if (!$field->inForm) {
                continue;
            }

            $validations = explode('|', $field->validations);
            $minMaxRules = '';
            foreach ($validations as $validation) {
                if (!Str::contains($validation, ['max:', 'min:'])) {
                    continue;
                }

                $validationText = substr($validation, 0, 3);
                $sizeInNumber = substr($validation, 4);

                $sizeText = ($validationText == 'min') ? 'minlength' : 'maxlength';
                if ($field->htmlType == 'number') {
                    $sizeText = $validationText;
                }

                $size = ",'$sizeText' => $sizeInNumber";
                $minMaxRules .= $size;
            }
            $this->commandData->addDynamicVariable('$SIZE$', $minMaxRules);

            $fieldTemplate = HTMLFieldGenerator::generateHTML($field, $this->templateType, $localized);

            if ($field->htmlType == 'selectTable') {
                $inputArr = explode(',', $field->htmlValues[1]);
                $columns = '';
                foreach ($inputArr as $item) {
                    $columns .= "'$item'".',';  //e.g 'email,id,'
                }
                $columns = substr_replace($columns, '', -1); // remove last ,

                $selectTable = $field->htmlValues[0];
                $tableName = $this->commandData->config->tableName;
                $variableName = Str::singular($selectTable).'Items'; // e.g $userItems

                $fieldTemplate = $this->generateViewComposer($tableName, $variableName, $columns, $selectTable);
            }

            if (!empty($fieldTemplate)) {
                $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $fieldTemplate,
                    $field
                );
                $this->htmlFields[] = $fieldTemplate;
            }
        }

        $templateData = get_template('scaffold.views.'.$templateName, $this->templateType);
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);
        $templateData = str_replace('$FIELDS$', implode("\n\n", $this->htmlFields), $templateData);

        return $templateData;
    }

    /**
     * 创建创建页
     */
    private function generateCreate()
    {
        $templateName = 'create';

        if ($this->commandData->isLocalizedTemplates()) {
            $templateName .= '_locale';
        }

        $templateData = get_template('scaffold.views.'.$templateName, $this->templateType);
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);
        $templateData = str_replace('$ZF_FIELD$', $this->generateFields(), $templateData);



        FileUtil::createFile($this->path, 'create.blade.php', $templateData);
        $this->commandData->commandInfo('create.blade.php created');
    }

    /**
     * 创建更新页
     */
    private function generateUpdate()
    {
        $templateName = 'edit';

        if ($this->commandData->isLocalizedTemplates()) {
            $templateName .= '_locale';
        }

        $templateData = get_template('scaffold.views.'.$templateName, $this->templateType);
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);
        $templateData = str_replace('$ZF_FIELD$', $this->generateFields(), $templateData);


        FileUtil::createFile($this->path, 'edit.blade.php', $templateData);
        $this->commandData->commandInfo('edit.blade.php created');
    }

    /**
     * 回撤
     * @param array $views
     */
    public function rollback($views = [])
    {
        $files = [
            'index.blade.php',
            'create.blade.php',
            'edit.blade.php',
        ];

        if (!empty($views)) {
            $files = [];
            foreach ($views as $view) {
                $files[] = $view.'.blade.php';
            }
        }

        if ($this->commandData->getAddOn('datatables')) {
            $files[] = 'datatables_actions.blade.php';
        }

        foreach ($files as $file) {
            if ($this->rollbackFile($this->path, $file)) {
                $this->commandData->commandComment($file.' file deleted');
            }
        }
    }
}
