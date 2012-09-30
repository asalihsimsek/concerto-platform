<?php
/*
  Concerto Platform - Online Adaptive Testing Platform
  Copyright (C) 2011-2012, The Psychometrics Centre, Cambridge University

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; version 2
  of the License, and not any of the later versions.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

if (!isset($ini)) {
    require_once'../../Ini.php';
    $ini = new Ini();
}
$logged_user = User::get_logged_user();
if ($logged_user == null) {
    echo "<script>location.reload();</script>";
    die(Language::string(278));
}

//////////
$class_name = "Test";
$edit_caption = Language::string(139);
$new_caption = Language::string(140);
//////////

if (!$logged_user->is_module_writeable($class_name))
    die(Language::string(81));

$oid = 0;
if (isset($_POST['oid']) && $_POST['oid'] != 0)
    $oid = $_POST['oid'];

$btn_cancel = "<button class='btnCancel' onclick='" . $class_name . ".uiEdit(0)'>" . Language::string(23) . "</button>";
$btn_delete = "<button class='btnDelete' onclick='" . $class_name . ".uiDelete($oid)'>" . Language::string(94) . "</button>";
$btn_save = "<button class='btnSave' onclick='" . $class_name . ".uiSave()'>" . Language::string(95) . "</button>";
$btn_save_new = "<button class='btnSaveNew' onclick='" . $class_name . ".uiSave(null,true)'>" . Language::string(510) . "</button>";

$caption = "";
$buttons = "";
if ($oid > 0) {
    $oid = $_POST['oid'];
    $obj = $class_name::from_mysql_id($oid);

    if (!$logged_user->is_object_editable($obj))
        die(Language::string(81));

    $caption = $edit_caption . " #" . $oid;
    $buttons = $btn_cancel . $btn_save . $btn_save_new . $btn_delete;
}
else {
    $obj = new $class_name();
    $caption = $new_caption;
    $buttons = "";
}

if ($oid != 0) {
    ?>
    <script>
        $(function(){
            Methods.iniIconButton(".btnGoToTop","arrow-1-n");
            Methods.iniIconButton("#btnExpand<?= $class_name ?>Description","arrowthick-1-s");
            Methods.iniIconButton(".btnCancel", "cancel");
            Methods.iniIconButton(".btnSave", "disk");
            Methods.iniIconButton(".btnSaveNew", "disk");
            Methods.iniIconButton(".btnDelete", "trash");
            Methods.iniIconButton(".btnDebugTest", "lightbulb");
            Methods.iniIconButton(".btnLogicVariables", "star");
            Methods.iniIconButton(".btnRunTest", "play");
            Methods.iniIconButton(".btnLogicToggleAll", "folder-collapsed");
            Methods.iniIconButton(".btnLogicToggleVarAssignment", "folder-collapsed");
    <?php
    if ($class_name::$exportable && $oid > 0) {
        ?>
                    Methods.iniIconButton(".btnExport", "arrowthickstop-1-n");
                    Methods.iniIconButton(".btnUpload", "gear");        
        <?php
    }
    ?>
            Methods.iniTooltips();
    <?php if ($oid != -1) { ?>
                Methods.iniCKEditor("#form<?= $class_name ?>TextareaDescription");
    <?php } ?>
        });
    </script>

    <fieldset class="padding ui-widget-content ui-corner-all margin">
        <legend class=""><b><?= $caption ?></b></legend>
        <table>
            <tr>
                <td class="noWrap horizontalPadding tdFormLabel">* <?= Language::string(70) ?>:</td>
                <td><span class="tooltip spanIcon ui-icon ui-icon-help" title="<?= Language::string(141) ?>"></span></td>
                <td class="fullWidth">
                    <div class="horizontalMargin">
                        <input type="text" id="form<?= $class_name ?>InputName" value="<?= $obj->name ?>" class="fullWidth ui-widget-content ui-corner-all" />
                    </div>
                </td>
            </tr>
            <?php
            if ($oid != -1) {
                ?>
                <tr>
                    <td class="noWrap horizontalPadding tdFormLabel"><?= Language::string(97) ?>:</td>
                    <td><span class="tooltip spanIcon ui-icon ui-icon-help" title="<?= Language::string(98) ?>"></span></td>
                    <td class="fullWidth">
                        <div class="horizontalMargin" align="center"><button id="btnExpand<?= $class_name ?>Description" class="btnExpand fullWidth" onclick="Methods.toggleExpand('#form<?= $class_name ?>DivDescription', this)"><?= Language::string(97) ?></button></div>
                        <div class="horizontalMargin" align="center" id="form<?= $class_name ?>DivDescription" style="display:none;">
                            <textarea id="form<?= $class_name ?>TextareaDescription" name="form<?= $class_name ?>TextareaDescription" class="fullWidth ui-widget-content ui-corner-all"><?= htmlspecialchars(stripslashes($obj->description)) ?></textarea>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
            <tr>
                <td class="noWrap horizontalPadding tdFormLabel"><?= Language::string(72) ?>:</td>
                <td><span class="tooltip spanIcon ui-icon ui-icon-help" title="<?= Language::string(142) ?>"></span></td>
                <td class="fullWidth">
                    <div class="horizontalMargin">
                        <select id="form<?= $class_name ?>SelectSharing" class="fullWidth ui-widget-content ui-corner-all">
                            <?php foreach (DS_Sharing::get_all() as $share) {
                                ?>
                                <option value="<?= $share->id ?>" <?= ($share->id == $obj->Sharing_id ? "selected" : "") ?>><?= $share->get_name() ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="noWrap horizontalPadding tdFormLabel"><?= Language::string(488) ?>:</td>
                <td><span class="tooltip spanIcon ui-icon ui-icon-help" title="<?= Language::string(489) ?>"></span></td>
                <td class="fullWidth">
                    <div class="horizontalMargin">
                        <input type="checkbox" id="form<?= $class_name ?>CheckboxOpen" value="1" <?= $obj->open == 1 ? "checked" : "" ?> />
                    </div>
                </td>
            </tr>

            <?php if ($oid > 0 && $logged_user->is_ownerhsip_changeable($obj)) {
                ?>
                <tr>
                    <td class="noWrap horizontalPadding tdFormLabel"><?= Language::string(71) ?>:</td>
                    <td><span class="tooltip spanIcon ui-icon ui-icon-help" title="<?= Language::string(143) ?>"></span></td>
                    <td class="fullWidth">
                        <div class="horizontalMargin">
                            <select id="form<?= $class_name ?>SelectOwner" class="fullWidth ui-widget-content ui-corner-all">
                                <option value="0" <?= (!$obj->has_Owner() ? "selected" : "") ?>>&lt;<?= Language::string(73) ?>&gt;</option>
                                <?php
                                $sql = $logged_user->mysql_list_rights_filter("User", "`User`.`lastname` ASC");
                                $z = mysql_query($sql);
                                while ($r = mysql_fetch_array($z)) {
                                    $owner = User::from_mysql_id($r[0]);
                                    ?>
                                    <option value="<?= $owner->id ?>" <?= ($obj->Owner_id == $owner->id ? "selected" : "") ?>><?= $owner->get_full_name() ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </td>
                </tr>
            </table>
        </fieldset>
        <?php
    }
    if ($oid != -1) {
        include Ini::$path_internal . "cms/view/Test_security.php";
        ?>
        <div id="div<?= $class_name ?>Variables"><?php include Ini::$path_internal . "cms/view/Test_variables.php"; ?></div>
        <?php
        include Ini::$path_internal . "cms/view/Test_logic.php";
    }
    ?>
    <div align="center">
        <?= $buttons ?>
    </div>
    <?php
    if ($oid != -1) {
        ?>
        <div class="divFormFloatingBar" align="right">
            <button class="btnGoToTop" onclick="location.href='#'"><?= Language::string(442) ?></button>
            <?= $btn_cancel ?>
            <?= $btn_delete ?>
            <?= $btn_save ?>
            <?= $btn_save_new ?>
            <?php
            if ($class_name::$exportable && $oid > 0) {
                ?>
                <button class="btnExport" onclick="<?= $class_name ?>.uiExport(<?= $oid ?>)"><?= Language::string(443) ?></button>
                <button class="btnUpload" onclick="<?= $class_name ?>.uiUpload(<?= $oid ?>)"><?= Language::string(383) ?></button>
                <?php
            }
            ?>
            <button class="btnLogicVariables" onclick="Test.uiShowVarsDialog()"><?= Language::string(144) ?></button>
            <button class="btnDebugTest" onclick="Test.uiIniDebug()"><?= Language::string(284) ?></button>
            <button class="btnRunTest" onclick="window.open('<?= Ini::$path_external . "?tid=" . $obj->id ?>','_blank')"><?= Language::string(362) ?></button>
            <button class="btnLogicToggleAll" onclick="Test.uiToggleAll()"><?= Language::string(401) ?></button>
            <button class="btnLogicToggleVarAssignment" onclick="Test.uiToggleVarAssignments()"><?= Language::string(454) ?></button>
        </div>
        <?php
    }
} else {
    ?>
    <div class="padding margin ui-state-error " align="center"><?= Language::string(123) ?></div>
    <?php
}
?>