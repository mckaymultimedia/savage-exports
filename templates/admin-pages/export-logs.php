<?php
/**
 * Logs' admin page template.
 *
 * @package Savage-Exports
 * @subpackage templates/admin-pages
 */
$upload_dir = wp_get_upload_dir()['basedir'];
$file_path = $upload_dir . '/savage-export-logs/export_log.csv';

if(file_exists($file_path)){

    // $file_path = plugin_dir_path(__FILE__).'export_log.csv';
  // Open a file
  $CSVfp = fopen($file_path, "r");
  if ($CSVfp !== FALSE) {
      ?>
      <div class="phppot-container">
          <table class="wp-list-table widefat fixed striped table-view-list ">
              <thead>
                  <tr>
                      <th colspan="2">Email</th>
                      <th>Export Type</th>
                      <th>Event</th>
                      <th>Date</th>
                  </tr>
              </thead>
  <?php
      while (! feof($CSVfp)) {
          $data = fgetcsv($CSVfp, 1000, ",");
          if (! empty($data)) {
  
              if($data[0]){
  
              
              ?>
              
              <tr class="data">
  
                  <td colspan="2"><?php echo $data[0]; ?></td>
                  <td><?php echo $data[1]; ?></td>
                  <td><?php echo $data[2]; ?></td>
                  <td><?php echo $data[3]; ?></td>
                 
              </tr>
   <?php
      }
   }
  ?>
  <?php
      }
      ?>
          </table>
      </div>
  <?php
  }
  fclose($CSVfp);
}

?>