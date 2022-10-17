<?php
if (!defined('SMD_TABBER')) {
	define("SMD_TABBER", 'smd_tabber');
}
if(@txpinterface == 'admin') {
	global $textarray, $smd_tabber_event, $smd_tabber_styles, $txp_user, $smd_tabber_callstack, $smd_tabber_uprivs, $smd_tabber_prevs;

	$smd_tabber_event = 'smd_tabber';
	$smd_tabber_prevs = '1';
	$smd_tabber_styles = array(
		'edit' => '#smd_tabber_wrapper { margin:0 auto; width:520px; }
.smd_tabber_equal { display:table; border-collapse:separate; margin:10px auto; border-spacing:8px; }
.smd_tabber_row { display:table-row; }
.smd_tabber_row div { display:table-cell; }
.smd_tabber_row .smd_tabber_label { width:150px; text-align:right; padding:2px 0 0 0; }
.smd_tabber_row .smd_tabber_value { width:350px; vertical-align:middle; }
#smd_tabber_select { margin-bottom:-20px; }
.smd_tabber_save { float:right; margin:10px 50px 0 0!important;}',
		'prefs' => '.smd_label { text-align: right!important; vertical-align: top; }
		',
	);
	register_callback('smd_tabber_welcome', 'plugin_lifecycle.'.$smd_tabber_event);

	$ulist = get_pref('smd_tabber_tab_privs', '');
	$allowed = ($ulist) ? explode(',', $ulist) : array();
	$levs = ($allowed) ? '1,2,3,4,5,6' : '1';
	if (empty($allowed) || in_array($txp_user, $allowed)) {
		add_privs($smd_tabber_event, $levs);
	}

	// Grab this here so that the privs are known immediately after manual install
	$smd_tabber_uprivs = safe_field('privs', 'txp_users', "name = '".doSlash($txp_user)."'");

	register_tab('admin', $smd_tabber_event, gTxt('smd_tabber_tab_name'));
	register_callback('smd_tabber_dispatch', $smd_tabber_event);

	// Do the tabbing deed
	if (smd_tabber_table_exist(1)) {
		register_callback('smd_tabber_css_link', 'admin_side', 'head_end');
		$smd_tabs = safe_rows('*', SMD_TABBER, '1=1 ORDER BY area, sort_order, name');

		// Yuk but no other way to get these.
		// NB: 'start' missing on purpose as it has no privs by default, so needs them adding
	 	$smd_areas = array('content', 'presentation', 'admin', 'extensions');

		foreach ($smd_tabs as $idx => $tab) {
			$name = $tab['name'];
			$area = $tab['area'];
			$areaname = strtolower(sanitizeForUrl($area));
			$area_privname = 'tab.' . $areaname;
			$create_top = (!in_array($areaname, $smd_areas));
			$tabname = strtolower(sanitizeForUrl($name));

			$eprivs = explode(',', $tab['view_privs']);
			$rights = in_array($smd_tabber_uprivs, $eprivs);

			if ($rights) {
				if ($create_top) {
					add_privs($area_privname, $smd_tabber_uprivs);
					$smd_areas[] = $areaname;
					if ($areaname != 'start') {
						$textarray['tab_'.$areaname] = $area;
					}
				}

				add_privs($tabname, $smd_tabber_uprivs);
				register_tab($areaname, $tabname, $name);
				register_callback('smd_tabber_render_tab', $tabname);
				$smd_tabber_callstack[$tabname] = array('name' => $name, 'page' => $tab['page'], 'style' => $tab['style']);
			}
		}
	}
}

// ------------------------
function smd_tabber_render_tab($evt, $stp) {
	global $smd_tabber_callstack, $pretext;

	$tab_info = $smd_tabber_callstack[$evt];

	// Allow multiple parse calls for any nested {replaced} content
	$parse_depth = intval(get_pref('smd_tabber_parse_depth', 1));

	pagetop($tab_info['name']);

	$html = safe_field('user_html', 'txp_page', "name='".doSlash($tab_info['page'])."'");
	if (!$html) {
		$html = '<txp:smd_tabber_edit_page />'.n.'<txp:smd_tabber_edit_style />';
	}

	// Hand over control to the Page code
	include_once txpath.'/publish.php';
	for ($idx = 0; $idx < $parse_depth; $idx++) {
		$html = parse($html);
	}
	echo $html;
}

// ------------------------
function smd_tabber_dispatch($evt, $stp) {
	if(!$stp or !in_array($stp, array(
			'smd_tabber_table_install',
			'smd_tabber_table_remove',
			'smd_tabber_css',
			'smd_tabber_prefs',
			'smd_tabber_prefsave',
			'smd_tabber_save',
			'smd_tabber_delete',
		))) {
		smd_tabber('');
	} else $stp();
}

// ------------------------
function smd_tabber_welcome($evt, $stp) {
	$msg = '';
	switch ($stp) {
		case 'installed':
			smd_tabber_table_install();
			$msg = 'Supertabs are go!';
			break;
		case 'deleted':
			smd_tabber_table_remove();
			break;
	}
	return $msg;
}

// ------------------------
function smd_tabber_css() {
	global $event;
	$name = doSlash(gps('name'));
	$css = safe_field('css', 'txp_css', "name='$name'");
	unset($name);

	if ($css) {
		header('Content-type: text/css');
		echo $css;
		exit();
	}
}

// ------------------------
function smd_tabber_css_link() {
	global $event, $smd_tabber_event;
	// Annoyingly, we need an extra test here in case the plugin has been deleted.
	// This callback is registered before plugin deletion but by the time it runs the table is gone
	if (smd_tabber_table_exist(1)) {
		$smd_tab = safe_field('style', SMD_TABBER, "name = '".doSlash($event)."'");
		echo ($smd_tab) ? n.'<link href="?event='.$smd_tabber_event.a.'step=smd_tabber_css'.a.'name='.$smd_tab.'" rel="stylesheet" type="text/css" />'.n : '';
	}
}

// ------------------------
function smd_tabber($msg='') {
	global $smd_tabber_event, $smd_tabber_uprivs, $smd_tabber_prevs, $smd_tabber_styles;

	pagetop(gTxt('smd_tabber_tab_name'), $msg);
	$pref_rights = in_array($smd_tabber_prevs, explode(',', $smd_tabber_uprivs));

	if (smd_tabber_table_exist(1)) {
		$tab_name = $tab_new_name = gps('smd_tabber_name');
		$area = $curr_page = $curr_style = $sort_order = '';
		$view_privs = array();
		$tablist = $smd_areas = array();
		$tab_prefix = get_pref('smd_tabber_tab_prefix', 'tabber_', 1);

		// Can't use the smd_tabs and smd_areas lists in the global scope 'cos they're stale / strtolower()ed
		$smd_tabs = safe_rows('*', SMD_TABBER, '1=1 ORDER BY area, sort_order, name');
		if ($smd_tabs) {
			foreach ($smd_tabs as $idx => $tab) {
				$tablist[$tab['area']][$tab['name']] = $tab['name'];
				$smd_areas[$tab['area']] = strtolower(sanitizeForUrl($tab['area']));

				if ($tab['name'] == $tab_name) {
					$sort_order = $tab['sort_order'];
					$area = $tab['area'];
					$view_privs = explode(',', $tab['view_privs']);
					$curr_page = $tab['page'];
					$curr_style = $tab['style'];
				}
			}
		}

		// Default to the current user level's privs for new items
		if ($tab_name == '') {
			$view_privs[] = $smd_tabber_uprivs;
		}

		// Build a select list of tab names, injecting optgroups above any areas
		$optgroups = (count($tablist) > 1); // Only add optgroups if there are tabs in more than one area
		$tabSelector = '';
		if ($tablist) {
			$tabSelector .= '<select name="smd_tabber_name" id="smd_tabber_name" onchange="submit(this.form);">';
			$tabSelector .= '<option value="">' . gTxt('smd_tabber_new_tab') . '</option>';
			$lastArea = '';
			$inGroups = false; // Is set to true when the first area is reached
			foreach ($tablist as $theArea => $theTabs) {
				if ($optgroups && $lastArea != $theArea) {
					$tabSelector .= ($inGroups) ? '</optgroup>' : '';
					$tabSelector .= '<optgroup label="'.$theArea.'">';
					$inGroups = true;
            }
            foreach($theTabs as $theTab => $tabName) {
	            $tabSelector .= '<option value="'.$theTab.'"'.(($theTab == $tab_name) ? ' selected="selected"' : '').'>' . $tabName . '</option>';
				}
			}
			$tabSelector .= (($optgroups) ? '</optgroup>' : '') . '</select>';
		}

		$areas = areas();
		$areas = array_merge($areas, $smd_areas);
		$area_list = array('' => gTxt('smd_tabber_new_area'));
		foreach ($areas as $idx => $alist) {
			$key = array_search($idx, $smd_areas);
			if ($key === false) {
				$area_list[$idx] = $idx;
			} else {
				$area_list[$key] = $key;
			}
		}

		$privs = get_groups();
		$privsel = smd_tabber_multi_select('smd_tabber_view_privs', $privs, $view_privs);

		$pages = safe_column('name', 'txp_page', "name like '".doSlash($tab_prefix)."%'");
		foreach ($pages as $idx => $page) {
			$pages[$idx] = str_replace($tab_prefix, '', $page);
		}
		$styles = safe_column('name', 'txp_css', "name like '".doSlash($tab_prefix)."%'");
		foreach ($styles as $idx => $style) {
			$styles[$idx] = str_replace($tab_prefix, '', $style);
		}

		$editcell = (($smd_tabs)
				? '<div class="smd_tabber_label">'
					. gTxt('smd_tabber_choose')
					. '</div><div class="smd_tabber_value">'
					. $tabSelector
					. (($tab_name != '')
						? sp . eLink(strtolower(sanitizeForUrl($tab_name)), '', '','', gTxt('View'))
						: '')
					. eInput($smd_tabber_event)
					.'</div>'
				: '');

		$pref_link = $pref_rights ? sp . eLink($smd_tabber_event, 'smd_tabber_prefs', '', '', '['.gTxt('smd_tabber_prefs').']') : '';

		// Edit form
		echo '<style type="text/css">' . $smd_tabber_styles['edit'] . '</style>';
		echo '<div id="smd_tabber_wrapper">';
		echo hed(gTxt('smd_tabber_heading'), 2);
		echo '<div class="smd_tabber_preflink">' . $pref_link . '</div>';
		echo '<form id="smd_tabber_select" action="index.php" method="post">';
		echo '<div class="smd_tabber_equal">';
		echo '<div class="smd_tabber_row">' . $editcell . '</div><!-- end row -->';
		echo '</div></form>';

		echo '<form name="smd_tabber_form" id="smd_tabber_form" action="index.php" method="post">';
		echo '<div class="smd_tabber_equal">';
		echo '<div class="smd_tabber_row">';
		echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_name') . '</div>';
		echo '<div class="smd_tabber_value">'
				. fInput('text', 'smd_tabber_new_name', $tab_new_name)
				. hInput('smd_tabber_name', $tab_name)
				. (($tab_name == '')
					? ''
					: sp . '<a href="?event='.$smd_tabber_event. a.'step=smd_tabber_delete' . a . 'smd_tabber_name='.urlencode($tab_name).'" class="smallerbox" onclick="return confirm(\''.gTxt('confirm_delete_popup').'\');">[x]</a>'
				)
				. '</div>'
				. '</div><!-- end row -->';
		echo '<div class="smd_tabber_row">';
		echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_sort_order') . '</div>';
		echo '<div class="smd_tabber_value">'
				. fInput('text', 'smd_tabber_sort_order', $sort_order)
				. '</div>'
				. '</div><!-- end row -->';
		echo '<div class="smd_tabber_row">';
		echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_area') . '</div>';
		echo '<div class="smd_tabber_value">'
				. selectInput('smd_tabber_area', $area_list, $area)
				. sp . fInput('text', 'smd_tabber_new_area', '')
				. '</div>'
				. '</div><!-- end row -->';
		echo '<div class="smd_tabber_row">';
		echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_view_privs') . '</div>';
		echo '<div class="smd_tabber_value">'
				. $privsel
				. '</div>'
				. '</div><!-- end row -->';
		echo '<div class="smd_tabber_row">';
		echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_page') . '</div>';
		echo '<div class="smd_tabber_value">'
				. selectInput('smd_tabber_page', $pages, $curr_page, true)
				. sp . (($curr_page) ? eLink('page', '', 'name', $curr_page, gTxt('edit')) : eLink('page', 'page_new', '', '', gTxt('create')) )
				. '</div>'
				. '</div><!-- end row -->';
		echo '<div class="smd_tabber_row">';
		echo '<div class="smd_tabber_label">' . gTxt('smd_tabber_style') . '</div>';
		echo '<div class="smd_tabber_value">'
				. selectInput('smd_tabber_style', $styles, $curr_style, true)
				. sp. (($curr_style) ? eLink('css', '', 'name', $curr_style, gTxt('edit')) : eLink('css', 'pour', '', '', gTxt('create')) )
				. '</div>'
				. '</div><!-- end row -->';

		echo '<div class="smd_tabber_row">';
		echo '<div class="smd_tabber_label">&nbsp;</div>';
		echo '<div class="smd_tabber_value">'
				. fInput('submit', 'submit', gTxt('save'), 'smd_tabber_save publish')
				. eInput($smd_tabber_event)
				. sInput('smd_tabber_save')
				. '</div>'
				. '</div><!-- end row -->';

		echo '</div><!-- end smd_tabber_equal -->';
		echo '</form>';
		echo '</div><!-- end smd_tabber_wrapper -->';
	} else {
		// Table not installed
		$btnInstall = '<form method="post" action="?event='.$smd_tabber_event.a.'step=smd_tabber_table_install" style="display:inline">'.fInput('submit', 'submit', gTxt('smd_tabber_tbl_install_lbl'), 'smallerbox').'</form>';
		$btnStyle = ' style="border:0;height:25px"';
		echo startTable('list');
		echo tr(tda(strong(gTxt('smd_tabber_prefs_some_tbl')).br.br
				.gTxt('smd_tabber_prefs_some_explain').br.br
				.gTxt('smd_tabber_prefs_some_opts'), ' colspan="2"')
		);
		echo tr(tda($btnInstall, $btnStyle));
		echo endTable();
	}
}

// ------------------------
function smd_tabber_save() {
	extract(doSlash(gpsa(array(
		'smd_tabber_name',
		'smd_tabber_new_name',
		'smd_tabber_area',
		'smd_tabber_new_area',
		'smd_tabber_page',
		'smd_tabber_style',
		'smd_tabber_sort_order',
	))));

	$vu = gps('smd_tabber_view_privs');
	$smd_tabber_view_privs = $vu ? doSlash(join(',', $vu)) : '';

	$msg = '';
	$theArea = ($smd_tabber_new_area == '') ? $smd_tabber_area : $smd_tabber_new_area;

	if ($smd_tabber_new_name == '') {
		$msg = array(gTxt('smd_tabber_need_name'), E_WARNING);
	} else {
		if ($theArea == '') {
			$msg = array(gTxt('smd_tabber_need_area'), E_WARNING);
		} else {
			$exists = safe_field('name', SMD_TABBER, "name='$smd_tabber_new_name'");
			$same = ($smd_tabber_name != $smd_tabber_new_name) && $exists;
			if ($same == false) {
				$_POST['smd_tabber_name'] = $smd_tabber_new_name;
				if ($smd_tabber_name == '') {
					safe_insert(SMD_TABBER, "name='$smd_tabber_new_name', sort_order='$smd_tabber_sort_order', area='".doSlash($theArea)."', page='$smd_tabber_page', style='$smd_tabber_style', view_privs='$smd_tabber_view_privs'");
					$msg = gTxt('smd_tabber_created');
				} else {
					safe_update(SMD_TABBER, "name='$smd_tabber_new_name', sort_order='$smd_tabber_sort_order', area='".doSlash($theArea)."', page='$smd_tabber_page', style='$smd_tabber_style', view_privs='$smd_tabber_view_privs'", "name='$smd_tabber_name'");
					$msg = gTxt('smd_tabber_saved');
				}
			} else {
				$msg = array(gTxt('smd_tabber_exists'), E_WARNING);
			}
		}
	}
	smd_tabber($msg);
}

// ------------------------
function smd_tabber_delete() {
	global $smd_tabber_event;

	$name = doSlash(gps('smd_tabber_name'));

	$ret = safe_delete(SMD_TABBER, "name='$name'");
	$msg = gTxt('smd_tabber_deleted');

	$_GET['smd_tabber_name'] = '';

	smd_tabber($msg);
}

// ------------------------
function smd_tabber_prefs($msg='') {
	global $smd_tabber_event, $smd_tabber_styles;

	pagetop(gTxt('smd_tabber_prefs_lbl'), $msg);

	$users = safe_rows('*', 'txp_users', '1=1 ORDER BY RealName');
	$privs = array('' => gTxt('smd_tabber_all_pubs'));
	foreach ($users as $idx => $user) {
		$privs[$user['name']] = $user['RealName'];
	}

	$curr_privs = explode(',', get_pref('smd_tabber_tab_privs', ''));
	$parse_depth = get_pref('smd_tabber_parse_depth', '1');
	$tab_prefix = get_pref('smd_tabber_tab_prefix', 'tabber_');

	echo '<style type="text/css">' . $smd_tabber_styles['prefs'] . '</style>';
	echo '<form action="index.php" method="post" name="smd_tabber_prefs_form">';
	echo startTable('list');
	echo tr(tda(hed(gTxt('smd_tabber_prefs_lbl'), 2), ' colspan="3"'));
	echo tr(
		tda(gTxt('smd_tabber_tab_privs'), ' class="smd_label"')
		. tda(smd_tabber_multi_select('smd_tabber_tab_privs', $privs, $curr_privs, 10))
	);
	echo tr(
		tda(gTxt('smd_tabber_tab_prefix'), ' class="smd_label"')
		. tda(fInput('text', 'smd_tabber_tab_prefix', $tab_prefix))
	);
	echo tr(
		tda(gTxt('smd_tabber_parse_depth'), ' class="smd_label"')
		. tda(fInput('text', 'smd_tabber_parse_depth', $parse_depth))
	);
	echo tr(tda(eLink($smd_tabber_event, '', '', '', gTxt('smd_tabber_cancel')), ' class="noline"') . tda(fInput('submit', '', gTxt('save'), 'publish'). eInput($smd_tabber_event).sInput('smd_tabber_prefsave'), ' class="noline"'));
	echo endTable();
	echo '</form>';
}

// ------------------------
function smd_tabber_prefsave() {
	$depth = intval(gps('smd_tabber_parse_depth'));
	$prefix = gps('smd_tabber_tab_prefix');
	$items = gps('smd_tabber_tab_privs');
	$privs = ($items) ? join(',', $items) : '';
	set_pref('smd_tabber_tab_privs', $privs, 'smd_tabber', PREF_HIDDEN, 'text_input');
	set_pref('smd_tabber_tab_prefix', $prefix, 'smd_tabber', PREF_HIDDEN, 'text_input');
	set_pref('smd_tabber_parse_depth', $depth, 'smd_tabber', PREF_HIDDEN, 'text_input');

	$msg = gTxt('preferences_saved');
	smd_tabber($msg);
}

// ------------------------
function smd_tabber_multi_select($name, $items, $sel=array(), $size='7') {
	$out = '<select name="'.$name.'[]" multiple="multiple" size="'.$size.'">'.n;
	foreach ($items as $idx => $item) {
		$out .= '<option value="'.$idx.'"'.((in_array($idx, $sel)) ? ' selected="selected"' : '').'>'.$item.'</option>'.n;
	}
	$out .= '</select>'.n;

	return $out;
}

// ------------------------
// Add tabber table if not already installed
function smd_tabber_table_install() {
	safe_create('smd_tabber', "
		name		VARCHAR(32) NOT NULL DEFAULT '',
		sort_order VARCHAR(32) 	NULL DEFAULT '',
		area		VARCHAR(32) 	NULL DEFAULT '',
		view_privs VARCHAR(32) NOT NULL DEFAULT '',
		page		VARCHAR(32) 	NULL DEFAULT '',
		style		VARCHAR(32) 	NULL DEFAULT '',

		PRIMARY KEY (`name`)
	");
}

// ------------------------
// Drop table if in database
function smd_tabber_table_remove() {
	safe_drop('smd_tabber');
}

// ------------------------
function smd_tabber_table_exist($all='') {
	if (function_exists('safe_exists')) {
		if (safe_exists('smd_tabber')) {
			return true;
		};
	} else {
		if ($all) {
			$tbls = array(SMD_TABBER => 6);
			$out = count($tbls);
			foreach ($tbls as $tbl => $cols) {
				if (gps('debug')) {
					echo "++ TABLE ".$tbl." HAS ".count(@safe_show('columns', $tbl))." COLUMNS; REQUIRES ".$cols." ++".br;
				}
				if (count(@safe_show('columns', $tbl)) == $cols) {
					$out--;
				}
			}
			return ($out===0) ? 1 : 0;
		} else {
			if (gps('debug')) {
				echo "++ TABLE ".SMD_TABBER." HAS ".count(@safe_show('columns', SMD_tabber))." COLUMNS;";
			}
			return(@safe_show('columns', SMD_TABBER));
		}
	}
}

// ***********
// PUBLIC TAGS
// ***********
// ------------------------
function smd_tabber_edit_page($atts, $thing=NULL) {
	global $smd_tabber_callstack, $event;

	extract(lAtts(array(
		'name'    => $event,
		'title'   => 'Edit page',
		'class'   => '',
		'html_id' => '',
		'wraptag' => '',
	),$atts));

	$tab_prefix = get_pref('smd_tabber_tab_prefix', 'tabber_');

	if (isset($atts['name'])) {
		$page = (strpos($name, $tab_prefix) !== false) ? $name : $tab_prefix.$name;
	} else {
		// Lookup the name of the page
		$ev = (strpos($name, $tab_prefix) !== false) ? str_replace($tab_prefix, '', $name) : $name;
		$page = (isset($smd_tabber_callstack[$ev])) ? $smd_tabber_callstack[$ev]['page'] : '';
	}
	$idx = 'name';
	$step = '';
	if (!$page) {
		$page = $idx = '';
		$step = 'page_new';
	}

	$lnk = eLink('page', $step, $idx, $page, $title);
	return ($wraptag) ? doTag($lnk, $wraptag, $class, '', $html_id) : $lnk;
}

// ------------------------
function smd_tabber_edit_style($atts, $thing=NULL) {
	global $smd_tabber_callstack, $event;

	extract(lAtts(array(
		'name'    => $event,
		'title'   => 'Edit CSS',
		'class'   => '',
		'html_id' => '',
		'wraptag' => '',
	),$atts));

	$tab_prefix = get_pref('smd_tabber_tab_prefix', 'tabber_');

	if (isset($atts['name'])) {
		$css = (strpos($name, $tab_prefix) !== false) ? $name : $tab_prefix.$name;
	} else {
		// Lookup the name of the stylesheet
		$ev = (strpos($name, $tab_prefix) !== false) ? str_replace($tab_prefix, '', $name) : $name;
		$css = (isset($smd_tabber_callstack[$ev])) ? $smd_tabber_callstack[$ev]['style'] : '';
	}

	$idx = 'name';
	$step = '';
	if (!$css) {
		$css = $idx = '';
		$step = 'pour';
	}

	$lnk = eLink('css', $step, $idx, $css, $title);
	return ($wraptag) ? doTag($lnk, $wraptag, $class, '', $html_id) : $lnk;
}