<?php

/**
 * This file is part of the Adapto Toolkit.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package adapto
 * @subpackage handlers
 *
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @copyright (c)2000-2004 Ivo Jansch
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *

 */

/**
 * Handler class for the edit action of an entity. The handler draws a
 * generic edit form for the given entity.
 *
 * @author ijansch
 * @author petercv
 * @package adapto
 * @subpackage handlers
 *
 */
class Adapto_Handler_Edit extends Adapto_ViewEditBase
{
    public $m_dialogSaveUrl = null; // defaulted to public
    public $m_buttonsource = null; // defaulted to public

    /**
     * Update action.
     *
     * @var string
     */
    private $m_updateAction = 'update';

    private $m_updateSessionStatus = SESSION_NESTED;

    /**
     * The action handler method.
     */
    function action_edit()
    {
        if (!empty($this->m_partial)) {
            $this->partial($this->m_partial);
            return;
        }

        $entity = &$this->m_entity;

        $record = $this->getRecord();

        if ($record === null) {
            $location = $entity->feedbackUrl("edit", ACTION_FAILED, $record);
            $entity->redirect($location);
        }

        // allowed to edit record?
        if (!$this->allowed($record)) {
            $this->renderAccessDeniedPage();
            return;
        }

        $record = $this->mergeWithPostvars($record);

        $this->notify("edit", $record);
        if ($entity->hasFlag(EF_LOCK)) {
            if ($entity->m_lock->lock($entity->primaryKey($record), $entity->m_table, $entity->getLockMode())) {
                $res = $this->invoke("editPage", $record, true);
            } else {
                $res = $entity->lockPage();
            }
        } else {
            $res = $this->invoke("editPage", $record, false);
        }

        $page = &$this->getPage();
        $page->addContent($entity->renderActionPage("edit", $res));
    }

    /**
     * Returns the update action, which is called when posting the edit form.
     *
     * Defaults to the 'update' action.
     *
     * @return string update action
     */

    public function getUpdateAction()
    {
        return $this->m_updateAction;
    }

    /**
     * Sets the update action which should be called when posting the edit form.
     *
     * @param string $action action name
     */

    public function setUpdateAction($action)
    {
        $this->m_updateAction = $action;
    }

    /**
     * check if there are postvars set that overwrite the record contents, this can
     * happen when a new selection is made using the select handler etc.
     *
     * @param array $record The record
     * @return array Record The merged record
     */
    function mergeWithPostvars($record)
    {
        $fetchedRecord = $this->m_entity->updateRecord('', NULL, NULL, true);

        /*
         * If any of the attributes is set to need a reload, we don't merge
         * with te postvars for that attribute
         */
        foreach ($fetchedRecord as $attrName => $value) {
            if ($attr = $this->m_entity->getAttribute($attrName)) {
                if ($attr->needsReload($record)) {
                    unset($fetchedRecord[$attrName]);
                }

            }
        }

        if (is_array($record)) {
            $record = array_merge($record, $fetchedRecord);
        }

        return $record;
    }

    /**
     * Register external files
     *
     */
    function registerExternalFiles()
    {
        $page = &$this->getPage();
        $ui = &$this->getUi();
        $page->register_script(Adapto_Config::getGlobal("atkroot") . "atk/javascript/tools.js");
        $page->register_script(Adapto_Config::getGlobal("atkroot") . "atk/javascript/formfocus.js");
        $page->register_loadscript("placeFocus();");
        $page->register_script(Adapto_Config::getGlobal("atkroot") . "atk/javascript/dhtml_formtools.js");
        $page->register_style($ui->stylePath("style.css"));
    }

    /**
     * Render the edit page
     *
     * @param Array $record The record to edit
     * @param Bool $locked Indicates whether the record is locked by the
     *                        current user.
     * @return String HTML code for the edit page
     */
    function editPage($record, $locked = FALSE)
    {
        $result = $this->getEditPage($record, $locked);

        if ($result !== false)
            return $result;
    }

    /**
     * Get the params for the edit page
     *
     * @param Array $record The record to edit
     * @param Bool $locked Indicates whether the record is locked by the
     *                        current user.
     * @return Array Array with parameters
     */
    function getEditParams($record, $locked = FALSE)
    {
        $entity = &$this->m_entity;
        $ui = &$entity->getUi();

        if (!is_object($ui)) {
            atkerror("ui object failure");
            return false;
        }

        $params = $entity->getDefaultActionParams($locked);
        $params['title'] = $entity->actionTitle('edit', $record);
        $params["formstart"] = $this->getFormStart();
        $params["header"] = $this->invoke("editHeader", $record);
        $params["content"] = $this->getContent($record);
        $params["buttons"] = $this->getFormButtons($record);
        $params["formend"] = $this->getFormEnd();
        return $params;
    }

    /**
     * This method draws a generic edit-page for a given record.
     *
     * @param array $record The record to edit.
     * @param boolean $locked Indicates whether the record is locked by the
     *                        current user.
     * @return String The rendered page as a string.
     */
    function getEditPage($record, $locked = FALSE)
    {
        $this->registerExternalFiles();

        $params = $this->getEditParams($record, $locked);

        if ($params === false)
            return false;

        return $this->renderEditPage($record, $params);
    }

    /**
     * Get the content
     *
     * @param Array $record
     * @return String The content
     */
    function getContent($record)
    {
        $entity = &$this->m_entity;

        $forceList = array();
        if (isset($entity->m_postvars['atkfilter'])) {
            $forceList = decodeKeyValueSet($entity->m_postvars['atkfilter']);
        }

        $suppressList = array();
        if (isset($entity->m_postvars['atksuppress'])) {
            $suppressList = $entity->m_postvars['atksuppress'];
        }

        $form = $this->editForm("edit", $record, $forceList, $suppressList, $entity->getEditFieldPrefix());

        return $entity->tabulate("edit", $form);
    }

    /**
     * Render the edit page
     *
     * @param Array $record
     * @param Array $params
     * @return String The rendered edit page
     */
    function renderEditPage($record, $params)
    {
        $entity = &$this->m_entity;
        $ui = &$entity->getUi();

        if (is_object($ui)) {
            $this->getPage()->setTitle(atktext('app_shorttitle') . " - " . $entity->actionTitle('edit', $record));

            $output = $ui->renderAction("edit", $params, $entity->m_module);
            $this->addRenderBoxVar("title", $entity->actionTitle('edit', $record));
            $this->addRenderBoxVar("content", $output);

            if ($this->getRenderMode() == "dialog") {
                $total = $ui->renderDialog($this->m_renderBoxVars);
            } else {
                $total = $ui->renderBox($this->m_renderBoxVars, $this->m_boxTemplate);
            }

            return $total;
        }
    }

    /**
     * Returns the current update session status.
     *
     * @see Adapto_Handler_Edit::setUpdateSessionStatus
     *
     * @return int session status
     */

    public function getUpdateSessionStatus()
    {
        return $this->m_updateSessionStatus;
    }

    /**
     * Sets the session status in which the update action gets executed.
     * By default the update action is called nested in the session stack.
     *
     * @param int $sessionStatus session status (e.g. SESSION_NESTED, SESSION_DEFAULT etc.)
     */

    public function setUpdateSessionStatus($sessionStatus)
    {
        $this->m_updateSessionStatus = $sessionStatus;
    }

    /**
     * Get the start of the form.
     *
     * @return String HTML The forms' start
     */
    function getFormStart()
    {
        $controller = &atkcontroller::getInstance();
        $controller->setEntity($this->m_entity);

        $formIdentifier = ((isset($this->m_partial) && $this->m_partial != "")) ? "dialogform" : "entryform";
        $formstart = '<form id="' . $formIdentifier . '" name="' . $formIdentifier . '" enctype="multipart/form-data" action="' . $controller->getPhpFile()
                . '?' . SID . '"' . ' method="post" onsubmit="return globalSubmit(this)">' . session_form($this->getUpdateSessionStatus());

        $formstart .= '<input type="hidden" name="' . $this->getEntity()->getEditFieldPrefix() . 'atkaction" value="' . $this->getUpdateAction() . '" />';
        $formstart .= '<input type="hidden" name="' . $this->getEntity()->getEditFieldPrefix() . 'atkprevaction" value="' . $this->getEntity()->m_action . '" />';
        $formstart .= '<input type="hidden" name="' . $this->getEntity()->getEditFieldPrefix() . 'atkcsrftoken" value="' . $this->getCSRFToken() . '" />';

        $formstart .= $controller->getHiddenVarsString();

        return $formstart;
    }

    /**
     * Get the end of the form.
     *
     * @return String HTML The forms' end
     */
    function getFormEnd()
    {
        return '</form>';
    }

    /**
     * Get the buttons for the current action form.
     *
     * @param array $record
     * @return array Array with buttons
     */
    function getFormButtons($record = null)
    {
        if ($this->m_partial == 'dialog' || $this->m_partial == 'editdialog') {
            $controller = &atkController::getInstance();
            $result = array();
            $result[] = $controller->getDialogButton('save', null, $this->getDialogSaveUrl(), $this->getDialogSaveParams());
            $result[] = $controller->getDialogButton('cancel');
            return $result;
        }

        // If no custom button source is given, get the default atkController.
        if ($this->m_buttonsource === null) {
            $this->m_buttonsource = &$this->m_entity;
        }

        return $this->m_buttonsource->getFormButtons("edit", $record);
    }

    /**
     * Create template field array for the given edit field.
     *
     * @param array  $fields all fields
     * @param int    $index  field index
     * @param string $mode   mode (add/edit)
     * @param string $tab    active tab
     *
     * @return array template field
     */
    function createTplField(&$fields, $index, $mode, $tab)
    {
        $field = &$fields[$index];

        // visible sections, both the active sections and the tab names (attribute that are
        // part of the anonymous section of the tab)
        $visibleSections = array_merge($this->m_entity->getActiveSections($tab, $mode), $this->m_entity->getTabs($mode));

        $tplfield = array();

        $classes = isset($field['class']) ? explode(" ", $field['class']) : array();
        if ($field["sections"] == "*") {
            $classes[] = "alltabs";
        } else if ($field["html"] == "section") {
            // section should only have the tab section classes
            foreach ($field["tabs"] as $section)
                $classes[] = "section_" . str_replace('.', '_', $section);
            if ($this->isSectionInitialHidden($field['name'], $fields))
                $classes[] = "atkAttrRowHidden";
        } else if (is_array($field["sections"])) {
            foreach ($field["sections"] as $section)
                $classes[] = "section_" . str_replace('.', '_', $section);
        }

        if (isset($field["initial_hidden"]) && $field["initial_hidden"]) {
            $classes[] = "atkAttrRowHidden";
        }

        $tplfield["class"] = implode(" ", $classes);
        $tplfield["tab"] = $tplfield["class"]; // for backwards compatibility

        // Todo fixme: initial_on_tab kan er uit, als er gewoon bij het opstarten al 1 keer showTab aangeroepen wordt (is netter dan aparte initial_on_tab check)
        // maar, let op, die showTab kan pas worden aangeroepen aan het begin.
        $tplfield["initial_on_tab"] = ($field["tabs"] == "*" || in_array($tab, $field["tabs"]))
                && (!is_array($field["sections"]) || count(array_intersect($field['sections'], $visibleSections)) > 0);

        // ar_ stands voor 'attribrow'.
        $tplfield["rowid"] = "ar_" . ($field['id'] != '' ? $field['id'] : getUniqueID("anonymousattribrows")); // The id of the containing row

        // check for separator
        if ($field["html"] == "-" && $index > 0 && $fields[$index - 1]["html"] != "-") {
            $tplfield["type"] = "line";
            $tplfield["line"] = "<hr>";
        } /* double separator, ignore */
 elseif ($field["html"] == "-") {
        } /* sections */
 elseif ($field["html"] == "section") {
            $tplfield["type"] = "section";
            list($tab, $section) = explode('.', $field["name"]);
            $tplfield["section_name"] = "section_{$tab}_{$section}";
            $tplfield["line"] = $this->getSectionControl($field, $mode);
        } /* only full HTML */
 elseif (isset($field["line"])) {
            $tplfield["type"] = "custom";
            $tplfield["line"] = $field["line"];
        } /* edit field */
 else {
            $tplfield["type"] = "attribute";

            if ($field["attribute"]->m_ownerInstance->getNumbering()) {
                $this->_addNumbering($field, $tplfield, $index);
            }

            /* does the field have a label? */
            if ((isset($field["label"]) && $field["label"] !== "AF_NO_LABEL") || !isset($field["label"])) {
                if (!isset($field["label"]) || empty($field["label"])) {
                    $tplfield["label"] = "";
                } else {
                    $tplfield["label"] = $field["label"];
                    if ($field["error"]) // TODO KEES
 {
                        $tplfield["error"] = $field["error"];
                    }
                }
            } else {
                $tplfield["label"] = "AF_NO_LABEL";
            }

            /* obligatory indicator */
            if ($field["obligatory"]) {
                // load images
                $theme = &atkinstance("atk.ui.atktheme");
                $reqimg = '<img align="top" src="' . $theme->imgPath("required_field.gif") . '" border="0"
                     alt="' . atktext("field_obligatory") . '" title="' . atktext("field_obligatory") . '">';

                $tplfield["label"];
                $tplfield["obligatory"] = $reqimg;
            }

            // Make the attribute and entity names available in the template.
            $tplfield['attribute'] = $field["attribute"]->fieldName();
            $tplfield['entity'] = $field["attribute"]->m_ownerInstance->atkEntityType();

            /* html source */
            $tplfield["widget"] = $field["html"];
            $editsrc = $field["html"];

            /* tooltip */
            $tooltip = $field["attribute"]->getToolTip();
            if ($tooltip) {
                $tplfield["tooltip"] = $tooltip;
                $editsrc .= $tooltip . "&nbsp;";
            }

            $tplfield['id'] = str_replace('.', '_', $this->m_entity->atkentitytype() . '_' . $field["id"]);

            $tplfield["full"] = $editsrc;

            $column = $field['attribute']->getColumn();
            $tplfield["column"] = $column;
        }

        // allow passing of extra arbitrary data, for example if a user overloads the editArray method
        // to pass custom extra data per attribute to the template
        if (isset($field['extra'])) {
            $tplfield['extra'] = $field['extra'];
        }

        return $tplfield;
    }

    /**
     * Function returns a generic html form for editing a record.
     *
     * @param String $mode         The edit mode ("add" or "edit").
     * @param array $record        The record to edit.
     * @param array $forceList     A key-value array used to preset certain
     *                             fields to a certain value.
     * @param array $suppressList  An array of fields that will be hidden.
     * @param String $fieldprefix  If set, each form element is prefixed with
     *                             the specified prefix (used in embedded
     *                             forms)
     * @param String $template		 The template to use for the edit form
     * @param boolean $ignoreTab   Ignore the tabs an attribute should be shown on.
     *
     * @return String the edit form as a string
     */
    function editForm($mode = "add", $record = NULL, $forceList = "", $suppressList = "", $fieldprefix = "", $template = "", $ignoreTab = false)
    {
        $entity = &$this->m_entity;

        /* get data, transform into form, return */
        $data = $entity->editArray($mode, $record, $forceList, $suppressList, $fieldprefix, $ignoreTab);
        // Format some things for use in tpl.
        /* check for errors and display them */
        $tab = $entity->getActiveTab();
        $error_title = "";
        $pk_err_attrib = array();
        $tabs = $entity->getTabs($entity->m_action);

        // Handle errors
        $errors = array();
        if (count($data['error']) > 0) {
            $error_title = '<b>' . atktext('error_formdataerror') . '</b>';

            foreach ($data["error"] as $error) {
                if ($error['err'] == "error_primarykey_exists") {
                    $pk_err_attrib[] = $error['attrib_name'];
                } else {
                    $type = (empty($error["entity"]) ? $entity->m_type : $error["entity"]);

                    if (count($tabs) > 1 && $error["tab"]) {
                        $tabLink = $this->getTabLink($entity, $error);
                        $error_tab = ' (' . atktext("error_tab") . ' ' . $tabLink . ' )';
                    } else {
                        $tabLink = null;
                        $error_tab = "";
                    }

                    if (is_array($error['label'])) {
                        $label = implode(', ', $error['label']);
                    } else if (!empty($error['label'])) {
                        $label = $error['label'];
                    } else if (!is_array($error['attrib_name'])) {
                        $label = $entity->text($error['attrib_name']);
                    } else {
                        $label = array();
                        foreach ($error['attrib_name'] as $attrib) {
                            $label[] = $entity->text($attrib);
                        }

                        $label = implode(", ", $label);
                    }

                    /* Error messages should be rendered in templates using message, label and the link to the tab. */
                    $err = array("message" => $error['msg'], "tablink" => $tabLink, "label" => $label);

                    $errors[] = $err;
                }
            }
            if (count($pk_err_attrib) > 0) // Make primary key error message
 {
                for ($i = 0; $i < count($pk_err_attrib); $i++) {
                    $pk_err_msg .= atktext($pk_err_attrib[$i], $entity->m_module);
                    if (($i + 1) < count($pk_err_attrib))
                        $pk_err_msg .= ", ";
                }
                $errors[] = array("label" => atktext("error_primarykey_exists"), "msg" => $pk_err_msg);
            }

        }

        /* display the edit fields */
        $fields = array();
        $errorFields = array();
        $attributes = array();
        for ($i = 0, $_i = count($data["fields"]); $i < $_i; $i++) {
            $field = &$data["fields"][$i];
            $tplfield = $this->createTplField($data["fields"], $i, $mode, $tab);
            $fields[] = $tplfield; // make field available in numeric array
            $params[$field["name"]] = $tplfield; // make field available in associative array
            $attributes[$field["name"]] = $tplfield; // make field available in associative array

            if ($field['error'])
                $errorFields[] = $field['id'];
        }

        $ui = &$this->getUi();
        $page = &$this->getPage();
        $page->register_script(Adapto_Config::getGlobal("atkroot") . "atk/javascript/formsubmit.js");

        // register fields that contain errornous values

        $page->register_scriptcode("var atkErrorFields = " . atkJSON::encode($errorFields) . ";");

        if (Adapto_Config::getGlobal('lose_changes_warning', true)) {
            // If we are in the save or update action the user has added a nested record, has done
            // a selection using the select handler or generated an error, in either way we assume
            // the form has been changed, so we always warn the user when leaving the page.
            $isChanged = 'false';
            if ((isset($record['atkerror']) && count($record['atkerror']) > 0)
                    || (isset($this->m_entity->m_postvars['__atkunloadhelper']) && $this->m_entity->m_postvars['__atkunloadhelper'])) {
                $isChanged = 'true';
            }

            $unloadText = addslashes($this->m_entity->text('lose_changes_warning'));
            $page->register_script(Adapto_Config::getGlobal("atkroot") . "atk/javascript/class.atkunloadhelper.js");
            $page->register_loadscript("new Adapto_.UnloadHelper('entryform', '{$unloadText}', {$isChanged});");
        }

        $result = "";

        foreach ($data["hide"] as $hidden) {
            $result .= $hidden;
        }

        $params["activeTab"] = $tab;
        $params["fields"] = $fields; // add all fields as a numeric array.
        $params["attributes"] = $attributes; // add all fields as an associative array

        $params["errortitle"] = $error_title;
        $params["errors"] = $errors; // Add the list of errors.
        atkdebug("Render editform - $template");
        if ($template) {
            $result .= $ui->render($template, $params);
        } else {
            $theme = &atkTheme::getInstance();
            if ($theme->tplPath("editform_common.tpl") > "") {
                $tabTpl = $this->_getTabTpl($entity, $tabs, $mode, $record);
                $params['fieldspart'] = $this->_renderTabs($fields, $tabTpl);
                $result .= $ui->render("editform_common.tpl", $params);
            } else {
                $result .= $ui->render($entity->getTemplate($mode, $record, $tab), $params);
            }
        }

        return $result;
    }

    /**
     * Get the link fo a tab
     *
     * @param atkEntity $entity The entity
     * @param Array $error
     * @return String HTML code with link
     */
    function getTabLink(&$entity, $error)
    {
        if (count($entity->getTabs($entity->m_action)) < 2)
            return '';
        return '<a href="javascript:void(0)" onclick="showTab(\'' . $error["tab"] . '\'); return false;">' . $this->getTabLabel($entity, $error["tab"]) . '</a>';
    }

    /**
     * Overrideable function to create a header for edit mode.
     * Similar to the admin header functionality.
     */
    function editHeader()
    {
        return "";
    }

    /**
     * The edit dialog
     *
     * @return String The edit dialog
     */
    function partial_dialog()
    {
        return $this->renderEditDialog();
    }

    /**
     * Render add dialog.
     *
     * @param array $record
     * @return string html
     */
    function renderEditDialog($record = null)
    {
        if ($record == null) {
            $record = $this->getRecord();
        }

        $this->setRenderMode('dialog');
        $result = $this->m_entity->renderActionPage("edit", $this->invoke("editPage", $record));
        return $result;
    }

    /**
     * Override the default dialog save URL.
     *
     * @param string $url dialog save URL
     */
    function setDialogSaveUrl($url)
    {
        $this->m_dialogSaveUrl = $url;
    }

    /**
     * Returns the dialog save URL.
     *
     * @return string dialog save URL
     */
    function getDialogSaveUrl()
    {
        if ($this->m_dialogSaveUrl != null) {
            return $this->m_dialogSaveUrl;
        } else {
            return partial_url($this->m_entity->atkEntityType(), 'update', 'dialog');
        }
    }

    /**
     * Returns the dialog save params. These are the same params that are part of the
     * dialog save url, but they will be appended at the end of the query string to
     * override any form variables with the same name!
     */
    function getDialogSaveParams()
    {
        $parts = parse_url($this->getDialogSaveUrl());
        $query = $parts['query'];
        $params = array();
        parse_str($query, $params);
        return $params;
    }
}
?>