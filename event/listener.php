<?php
/**
*
* @package updateattachment v 1.0.0
* @copyright (c) 2024 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace tatiana5\updateattachment\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

//require '/home/u523962/geoip2/vendor/autoload.php';
//use GeoIp2\Database\Reader;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\attachment\manager */
	protected $attachment_manager;

	protected $sql_ary;

	public function __construct(\phpbb\auth\auth $auth,
								\phpbb\config\config $config,
								\phpbb\db\driver\driver_interface $db,
								\phpbb\template\template $template,
								\phpbb\request\request $request,
								\phpbb\user $user,
								\phpbb\attachment\manager $attachment_manager)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->template = $template;
		$this->request = $request;
		$this->user = $user;
		$this->attachment_manager = $attachment_manager;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'								=> 'load_language_on_setup',
			'core.modify_attachment_sql_ary_on_upload'		=> 'modify_attachment_sql_ary_on_upload',
			'core.modify_attachment_data_on_upload'			=> 'modify_attachment_data_on_upload',
		);
	}

	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'tatiana5/updateattachment',
			'lang_set' => 'updateattachment',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function modify_attachment_sql_ary_on_upload($event)
	{
		$attach_id_old = (int) $this->request->variable('update_file', 0);

		if ($attach_id_old > 0)
		{
			$this->sql_ary = $sql_ary = $event['sql_ary'];
			unset($sql_ary['attach_comment']);

			$sql = 'SELECT attach_id, is_orphan, filesize, physical_filename, thumbnail
						FROM ' . ATTACHMENTS_TABLE . '
						WHERE attach_id = ' . $attach_id_old;
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($row !== false)
			{
				$this->db->sql_query('UPDATE ' . ATTACHMENTS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . ' WHERE attach_id = ' . $attach_id_old );

				$this->attachment_manager->unlink($row['physical_filename'], 'file', false);

				if ($row['thumbnail'])
				{
					$this->attachment_manager->unlink($row['physical_filename'], 'thumbnail', false);
				}

				if (!$row['is_orphan'])
				{
					$this->config->set('upload_dir_size', $this->config['upload_dir_size'] - $row['filesize'] + $sql_ary['filesize'], false);
				}
			}
		}
	}

	public function modify_attachment_data_on_upload($event)
	{
		$attach_id_old = (int) $this->request->variable('update_file', 0);

		if ($attach_id_old > 0)
		{
			$attachment_data = $event['attachment_data'];

			$attach_id_new = (int) $attachment_data[0]['attach_id'];
			$this->db->sql_query('DELETE FROM ' . ATTACHMENTS_TABLE . ' WHERE attach_id = ' . $attach_id_new);
			array_splice($attachment_data, 0, 1);

			foreach ($attachment_data as $key => $value)
			{
				if ($value['attach_id'] == $attach_id_old)
				{
					$attachment_data[$key]['real_filename'] = $this->sql_ary['real_filename'];
				}
			}

			$event['attachment_data'] = $attachment_data;
		}
	}
}
