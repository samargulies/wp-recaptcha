<?php

    if (defined('ALLOW_INCLUDE') === false)
        die('no direct acess');

?>

<a name="mailhide"></a>
<h2><?php _e('MailHide Options', 'recaptcha'); ?></h2>
<p><?php _e('One common misconception about MailHide is that it edits your email addresses on the database. This is false, your actual content is never actually modified. Instead, it is "filtered" such that it appears modified to the reader.', 'recaptcha'); ?></p>

<form method="post" action="options.php">
   <?php settings_fields('mailhide_options_group'); ?>

   <h3><?php _e('Authentication', 'recaptcha'); ?></h3>
   <p><?php _e('These keys are required before you are able to do anything else.', 'recaptcha'); ?> <?php _e('You can get the keys', 'recaptcha'); ?> <a href="http://mailhide.recaptcha.net/apikey" title="<?php _e('Get your reCAPTCHA API Keys', 'recaptcha'); ?>"><?php _e('here', 'recaptcha'); ?></a>.</p>
   <p><?php _e('Be sure not to mix them up! The public and private keys are not interchangeable!'); ?></p>
   
   <table class="form-table">
      <tr valign="top">
         <th scope="row"><?php _e('Public Key', 'recaptcha'); ?></th>
         <td>
            <input type="text" name="mailhide_options[public_key]" size="40" value="<?php echo $this->options['public_key']; ?>" />
         </td>
      </tr>
      <tr valign="top">
         <th scope="row"><?php _e('Private Key', 'recaptcha'); ?></th>
         <td>
            <input type="text" name="mailhide_options[private_key]" size="40" value="<?php echo $this->options['private_key']; ?>" />
         </td>
      </tr>
   </table>
   
   <h3><?php _e('General Options', 'recaptcha'); ?></h3>
   <table class="form-table">
      <tr valign="top">
         <th scope="row"><?php _e('Use MailHide in', 'recaptcha'); ?></th>
         <td>
            <input type="checkbox" name="mailhide_options[use_in_posts]" id="mailhide_options[use_in_posts]" value="1" <?php checked('1', $this->options['use_in_posts']); ?> />
            <label for="mailhide_options[use_in_posts]">Posts and Pages</label><br />
            
            <input type="checkbox" name="mailhide_options[use_in_comments]" id="mailhide_options[use_in_comments]" value="1" <?php checked('1', $this->options['use_in_comments']); ?> />
            <label for="mailhide_options[use_in_comments]">Comments</label><br />
            
            <input type="checkbox" name="mailhide_options[use_in_rss]" id="mailhide_options[use_in_rss]" value="1" <?php checked('1', $this->options['use_in_rss']); ?> />
            <label for="mailhide_options[use_in_rss]">RSS Feed of Posts and Pages</label><br />
            
            <input type="checkbox" name="mailhide_options[use_in_rss_comments]" id="mailhide_options[use_in_rss_comments]" value="1" <?php checked('1', $this->options['use_in_rss_comments']); ?> />
            <label for="mailhide_options[use_in_rss_comments]">RSS Feed of Comments</label><br />
         </td>
      </tr>
      
      <tr valign="top">
         <th scope="row"><?php _e('Target', 'recaptcha'); ?></th>
         <td>
            <input type="checkbox" id="mailhide_options[bypass_for_registered_users]" name="mailhide_options[bypass_for_registered_users]" value="1" <?php checked('1', $this->options['bypass_for_registered_users']); ?> />
            <label for="mailhide_options[bypass_for_registered_users]"><?php _e('Show actual email addresses to Registered Users who can', 'recaptcha'); ?></label>
            <?php $this->capabilities_dropdown(); // <select> of capabilities ?>
         </td>
      </tr>
   </table>
   
   <h3><?php _e('Presentation', 'recaptcha'); ?></h3>
   <table class="form-table">
      <tr valign="top">
         <th scope="row"><?php _e('Replace Link With', 'recaptcha'); ?></th>
         <td>
            <input type="text" name="mailhide_options[replace_link_with]" size="70" value="<?php echo $this->options['replace_link_with']; ?>" />
         </td>
      </tr>
      
      <tr valign="top">
         <th scope="row"><?php _e('Replace Title With', 'recaptcha'); ?></th>
         <td>
            <input type="text" name="mailhide_options[replace_title_with]" size="70" value="<?php echo $this->options['replace_title_with']; ?>" />
         </td>
      </tr>
   </table>

   <p class="submit"><input type="submit" class="button-primary" title="<?php _e('Save MailHide Options') ?>" value="<?php _e('Save Changes') ?> &raquo;" /></p>
</form>