<?php
/*
  Plugin Name: Gravity Forms Data Persistence Add-On
  Plugin URI: http://asthait.com
  Description: This is an <a href="http://www.gravityforms.com/" target="_blank">Gravity Form</a> plugin. A big limitation with Gravity Form is, in case of big multipage forms, if you close or refresh the page during somewhere midle of some step. all the steps data will loose. this plugin solves that problem.
  Author: Astha Team
  Version: 3.0
  Author URI: http://asthait.com
 */



add_action("gform_post_paging", "page_changed", 10, 3);

function page_changed($form, $coming_from_page, $current_page) {
    if ($form['isPersistent']) {
        if (is_user_logged_in()) {
            $option_key = getFormOptionKeyForGF($form);
            update_option($option_key, json_encode($_POST));
        }
    }
}

add_filter("gform_pre_render", "pre_populate_the_form");

function pre_populate_the_form($form) {
    if ($form['isPersistent']) {
        $option_key = getFormOptionKeyForGF($form);
        if (get_option($option_key)) {
            $_POST = json_decode(get_option($option_key), true);
        }
    }


    return $form;
}

add_action("gform_post_submission", "set_post_content", 10, 2);

function set_post_content($entry, $form) {
    if ($form['isPersistent']) {
        //Update form data in wp_options table
        if (is_user_logged_in()) {
            $option_key = getFormOptionKeyForGF($form);
            update_option($option_key, json_encode($_POST));

            $entry_option_key = getEntryOptionKeyForGF($entry);
            if (get_option($entry_option_key)) {
                //Delete old entry from GF tables
                if (!$form['isEnableMulipleEntry']) {
                    delete_entry_from_gf_tables(get_option($entry_option_key));
                }
                
            }
        }

        //Update entry in wp_options table

        update_option($entry_option_key, $entry['id']);
    }
}

function delete_entry_from_gf_tables($lead_id) {
    global $wpdb;
    $table_lead = $wpdb->prefix . "rg_lead";
    $table_detail = $wpdb->prefix . "rg_lead_detail";
    $table_detail_long = $wpdb->prefix . "rg_lead_detail_long";
    $table_lead_meta = $wpdb->prefix . "rg_lead_meta";
    $table_lead_notes = $wpdb->prefix . "rg_lead_notes";

    $sql = "DELETE FROM $table_detail_long WHERE lead_detail_id in( SELECT id FROM $table_detail WHERE lead_id = $lead_id )";
    $wpdb->query($sql);

    $sql = "DELETE FROM $table_lead_notes WHERE  lead_id = $lead_id";
    $wpdb->query($sql);

    $sql = "DELETE FROM $table_lead_meta WHERE  lead_id = $lead_id";
    $wpdb->query($sql);

    $sql = "DELETE FROM $table_detail WHERE  lead_id = $lead_id";
    $wpdb->query($sql);

    $sql = "DELETE FROM $table_lead WHERE  id = $lead_id";
    $wpdb->query($sql);
}

function getFormOptionKeyForGF($form) {

    global $current_user;
    get_currentuserinfo();

    $option_key = $current_user->user_login . '_GF_' . $form['id'];

    return $option_key;
}

function getEntryOptionKeyForGF($entry) {

    global $current_user;
    get_currentuserinfo();

    $option_key = $current_user->user_login . '_GF_' . $form['id'] . '_entry';

    return $option_key;
}

//Add persistent checkbox to the form settings
add_action("gform_advanced_settings", "persistency_settings", 10, 2);

function persistency_settings($position, $form_id) {

    //create settings on position 50 (right after Admin Label)
    if ($position == 600) {
        ?>
        <li>
            <input type="checkbox" id="form_persist_value" onclick="SetFormPersistency();" /> Enable form persistence
            <label for="form_persist_value">              
                <?php gform_tooltip("form_persist_tooltip") ?>
            </label>

        </li>
        <li>
            <input type="checkbox" id="form_enable_multiple_entry_entry" onclick="SetFormMultipleEntry();" /> Enable multi entry from same user while form is persistent
            <label for="form_enable_multiple_entry">              
                <?php gform_tooltip("form_enable_multiple_entry_tooltip") ?>
            </label>

        </li>
        <?php
    }
}

//Action to inject supporting script to the form editor page
add_action("gform_editor_js", "editor_script_persistency");

function editor_script_persistency() {
    ?>
    <script type='text/javascript'>
                
        function SetFormPersistency(){
            form.isPersistent = jQuery("#form_persist_value").is(":checked");
        }
        function SetFormMultipleEntry(){
            form.isEnableMulipleEntry = jQuery("#form_enable_multiple_entry_entry").is(":checked");
        }
                
        jQuery("#form_persist_value").attr("checked", form.isPersistent);       
        jQuery("#form_enable_multiple_entry_entry").attr("checked", form.isEnableMulipleEntry);    
        
    </script>
    <?php
}

//Filter to add a new tooltip
add_filter('gform_tooltips', 'add_persistency_tooltips');

function add_persistency_tooltips($tooltips) {
    $tooltips["form_persist_tooltip"] = "<h6>Persistency</h6>Check this box to make this form persistant";
    $tooltips["form_enable_multiple_entry_tooltip"] = "<h6>Persistency</h6>This will allow multiple entry from same user but, user can't edit their last";
    return $tooltips;
}
