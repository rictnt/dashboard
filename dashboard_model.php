<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.

 ---------------------------------------------------------------------
 Emoncms - open source energy visualisation
 Part of the OpenEnergyMonitor project:
 http://openenergymonitor.org
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

/*
 * Create a new user dashboard
 *
 */
class Dashboard
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function create($userid)
    {
        $userid = (int) $userid;
        $this->mysqli->query("INSERT INTO dashboard (`userid`,`alias`) VALUES ('$userid','')");
        return $this->mysqli->insert_id;
    }

    public function delete($id)
    {
        $id = (int) $id;
        $result = $this->mysqli->query("DELETE FROM dashboard WHERE id = '$id'");
        return $result;
    }

    public function dashclone($userid, $id)
    {
        $userid = (int) $userid;
        $id = (int) $id;

        // Get content, name and description from origin dashboard
        $result = $this->mysqli->query("SELECT content,name,description,height FROM dashboard WHERE userid = '$userid' AND id='$id'");
        $row = $result->fetch_array();

        // Name for cloned dashboard
        $name = $row['name']._(' clone');

        $this->mysqli->query("INSERT INTO dashboard (`userid`,`content`,`name`,`description`,`height`) VALUES ('$userid','{$row['content']}','$name','{$row['description']}','{$row['height']}')");

        return $this->mysqli->insert_id;
    }

    public function get_list($userid, $public, $published)
    {
        $userid = (int) $userid;

        $qB = ""; $qC = "";
        if ($public==true) $qB = " and public=1";
        if ($published==true) $qC = " and published=1";
        if (!$result = $this->mysqli->query("SELECT id, name, alias, description, main, published, public, showdescription FROM dashboard WHERE userid='$userid'".$qB.$qC)) {
          return array();
        }
        
        $list = array();
        while ($row = $result->fetch_object())
        {
        $list[] = array (
            'id' => (int) $row->id,
            'name' => $row->name,
            'alias' => $row->alias,
            'showdescription' => (bool) $row->showdescription,
            'description' => $row->description,
            'main' => (bool) $row->main,
            'published'=> (bool) $row->published,
            'public'=> (bool) $row->public
        );
        }
        return $list;
    }

    public function set_content($userid, $id, $content, $height)
    {
        $userid = (int) $userid;
        $id = (int) $id;
        $height = (int) $height;

        $result = $this->mysqli->query("SELECT * FROM dashboard WHERE userid = '$userid' AND id='$id'");
        $row = $result->fetch_array();
        if ($row) {
            $stmt = $this->mysqli->prepare("UPDATE dashboard SET content=?, height=? WHERE userid=? AND id=?");
            $stmt->bind_param("siii", $content, $height, $userid, $id);
            $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows>0){
                return array('success'=>true, 'message'=>'Dashboard updated');
            }
        }
        return array('success'=>false, 'message'=>'Dashboard not updated');
    }

    public function set($userid,$id,$fields)
    {
        $userid = (int) $userid;
        $id = (int) $id;
        $fields = json_decode(stripslashes($fields));

        $array = array();

        // content, height, name, alias, description, main, public, published, showdescription
        // Repeat this line changing the field name to add fields that can be updated:

        if (isset($fields->height)) $array[] = "`height` = '".intval($fields->height)."'";
        if (isset($fields->name)) $array[] = "`name` = '".preg_replace('/[^\p{L}_\p{N}\s-]/u','',$fields->name)."'";
        if (isset($fields->alias)) $array[] = "`alias` = '".preg_replace('/[^\p{L}_\p{N}\s-]/u','',$fields->alias)."'";
        if (isset($fields->description)) $array[] = "`description` = '".preg_replace('/[^\p{L}_\p{N}\s-]/u','',$fields->description)."'";
        if (isset($fields->backgroundcolor)) $array[] = "`backgroundcolor` = '".preg_replace('/[^0-9a-f]/','', strtolower($fields->backgroundcolor))."'";
        if (isset($fields->gridsize)) $array[] = "`gridsize` = '".preg_replace('/[^0-9]/','', $fields->gridsize)."'";

        if (isset($fields->main))
        {
            $main = (bool)$fields->main;
            if ($main) $this->mysqli->query("UPDATE dashboard SET main = FALSE WHERE userid='$userid' and id<>'$id'");
            $array[] = "`main` = '".$main ."'";
        }

        if (isset($fields->public)) $array[] = "`public` = '".((bool)$fields->public)."'";
        if (isset($fields->published)) $array[] = "`published` = '".((bool)$fields->published)."'";
        if (isset($fields->showdescription)) $array[] = "`showdescription` = '".((bool)$fields->showdescription)."'";
        // Convert to a comma seperated string for the mysql query
        $fieldstr = implode(",",$array);

        $this->mysqli->query("UPDATE dashboard SET ".$fieldstr." WHERE userid='$userid' and `id` = '$id'");

        if ($this->mysqli->affected_rows>0){
            return array('success'=>true, 'message'=>'Field updated');
        } else {
            return array('success'=>false, 'message'=>'Field could not be updated');
        }
    }

    // Return the main dashboard from $userid
    public function get_main($userid)
    {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT * FROM dashboard WHERE userid='$userid' and main=TRUE");
        return $result->fetch_array();
    }

    public function get($id)
    {
        $id = (int) $id;
        $result = $this->mysqli->query("SELECT * FROM dashboard WHERE id='$id'");
        return $result->fetch_array();
    }

    // Returns the $id dashboard from $userid
    public function get_from_alias($userid, $alias)
    {
        $userid = (int) $userid;
        $alias = preg_replace('/[^\p{L}_\p{N}\s-]/u','',$alias);
        $result = $this->mysqli->query("SELECT * FROM dashboard WHERE userid='$userid' and alias='$alias'");
        return $result->fetch_array();
    }

    public function build_menu_array($location)
    {
        global $session;
        $userid = (int) $session['userid'];

        $public = 0; $published = 0;

        if (isset($session['profile']) && $session['profile']==1) {
            $dashpath = $session['username'];
            $public = !$session['write'];
            $published = 1;
        } else {
            $dashpath = 'dashboard/'.$location;
        }

        $dashboards = $this->get_list($userid, $public, $published);
        $menu = array();
        foreach ($dashboards as $dashboard)
        {
            // Check show description
            $desc = '';
            if ($dashboard['showdescription']) {
                $desc = $dashboard['description'];
            }

            // Set URL using alias or id
            if ($dashboard['alias']) {
                $aliasurl = "/".$dashboard['alias'];
            } else {
                $aliasurl = '&id='.$dashboard['id'];
            }

            // Build the menu item
            $menu[] = array('name' => $dashboard['name'], 'desc'=> $desc, 'published'=> $dashboard['published'], 'path' => $dashpath.$aliasurl, 'order' => "-1".$dashboard['name']);
        }
        return $menu;
    }

}

