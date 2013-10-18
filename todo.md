== Table description
th_msp_publishing
publishing_id   blog_id   object_id   object_type   group_id   parent   synchronized

CREATE TABLE IF NOT EXISTS `th_msp_publishing` (
  `publishing_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blog_id` bigint(20) unsigned NOT NULL,
  `object_id` bigint(20) unsigned NOT NULL,
  `object_type` varchar(20) NOT NULL DEFAULT 'post',
  `group_id` bigint(20) unsigned NOT NULL,
  `parent` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `synchronized` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`publishing_id`),
  KEY `blog_object_type` (`blog_id`,`object_id`,`object_type`)
)

th_msp_publishing_group
group_id   group_name

CREATE TABLE IF NOT EXISTS `th_msp_publishing_group` (
  `group_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(20) NOT NULL,
  PRIMARY KEY (`group_id`)
)

private function get_group_id( object_id, object_type, blog_id ) {
	1. select group_id from th_msp_publishing where blog_id=blog_id AND object_id=object_id AND object_type=object_type
	2. If result, return result.
	3. Else get group_name than insert
	4. Return group_id
}

private function insert_publishing( blog_id, object_id, object_type, group_id, parent, synchronized )
private function delete_publishing( blog_id, object_id, object_type )
private function delete_publishing_group( group_id )
private function set_synchronized_status( blog_id, object_id, object_type, synchronized_status )
