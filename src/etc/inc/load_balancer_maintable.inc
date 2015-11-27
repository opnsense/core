<?php

/*
        Copyright (C) 2014 Deciso B.V.
        Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>.
        All rights reserved.

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions are met:

        1. Redistributions of source code must retain the above copyright notice,
           this list of conditions and the following disclaimer.

        2. Redistributions in binary form must reproduce the above copyright
           notice, this list of conditions and the following disclaimer in the
           documentation and/or other materials provided with the distribution.

        THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
        AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
        OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.
*/

class MainTable {
	private $headers = array();
//	private $columns = array();
	private $columns = 0;
	private $rows = 0;
	private $content = array();
	private $edit_uri = '';
	private $my_uri = '';
	private $buttons = array('move' => false, 'edit' => false, 'del' => false, 'dup' => false);

	function add_column($header, $cname, $width) {
//		$this->column[] = array('header' => $header, 'cname' => $cname, 'width' => $width)
		$this->headers[] = $header;
		$this->cname[] = $cname;
		$this->width[] = $width;
		$this->columns++;
	}

	function add_content_array($rows) {
		foreach($rows as $row) {
			$this->content[] = $row;
			$this->rows++;
		}
	}
	function add_button($name) {
		if (isset($this->buttons[$name])) {
			$this->buttons[$name] = true;
		}
	}
	function edit_uri($uri) {
		$this->edit_uri = $uri;
	}

	function my_uri($uri) {
		$this->my_uri = $uri;
	}

	function display() {
		echo "<!-- begin content table -->\n";
		echo "<table class=\"table table-striped table-sort\" >\n";
		echo "  <!-- begin content table header -->\n";
		echo $this->display_header();
		echo "  <!-- end content table header -->\n";
		echo "  <!-- begin content table rows -->\n";
		echo $this->display_rows();
		echo "  <!-- end content table rows -->\n";
		echo "  <!-- begin content table footer -->\n";
		echo $this->display_footer();
		echo "  <!-- end content table footer -->\n";
		echo "</table>\n";
		echo "<!-- end content table -->\n";
	}

	private function display_header() {
		global $g;
		echo "<tr>\n";
		for ($col = 0; $col < $this->columns - 1; $col++) {
			echo "  <td width=\"{$this->width[$col]}%\" class=\"listhdrr\">{$this->headers[$col]}</td>\n";
		}
		echo "  <td width=\"{$this->width[$this->columns - 1]}%\" class=\"listhdr\">{$this->headers[$this->columns - 1]}</td>\n";
		echo "  <td width=\"10%\" class=\"list\">\n";

		echo "  </td>\n";
		echo "</tr>\n";

	}
	private function display_rows() {
		global $g;
		$cur_row = 0;
		foreach ($this->content as $row) {
			echo "<tr>\n";
			for ($col = 0; $col < $this->columns - 1; $col++) {
				if ($col == 0) {
					$cl = 'listlr';
				} else {
					$cl = 'listr';
				}
				echo "  <td class=\"{$cl}\" onclick=\"fr_toggle({$cur_row})\" id=\"frd{$cur_row}\" ondblclick=\"document.location='{$this->edit_uri}?id={$cur_row}'\">\n";
				if (is_array($row[$this->cname[$col]])) {
					foreach ($row[$this->cname[$col]] as $data) {
						echo "    {$data}<br />\n";
					}
				} else {
					echo "    " . $row[$this->cname[$col]] . "\n";
				}
				echo "  </td>\n";
			}
			echo "  <td class=\"listbg\" onclick=\"fr_toggle({$cur_row})\" id=\"frd{$cur_row}\" ondblclick=\"document.location='{$this->edit_uri}?id={$cur_row}'\">\n";
			echo "    <font color=\"#FFFFFF\">{$row[$this->cname[$this->columns - 1]]}</font>\n";
			echo "  </td>\n";
			echo "  <td class=\"list nowrap\">\n";
			$this->display_buttons($cur_row);
			echo "  </td>\n";
			echo "</tr>\n";

			$cur_row++;
		}
	}
	private function display_footer() {
		global $g;

	}
	private function display_buttons($row) {

		if ($this->buttons['move'])
			echo $this->display_button('move', $row);
		if ($this->buttons['edit'])
			echo $this->display_button('edit', $row);

		if ($this->buttons['del'])
			echo $this->display_button('del', $row);
		if ($this->buttons['dup'])
			echo $this->display_button('dup', $row);

	}
	private function display_button($button, $row) {
		global $g;

		switch ($button) {
			case "move": {
				echo "<button name=\"move_{$row}\" type=\"submit\" title=\"".gettext('Move selected entries before this entry')."\" onmouseover=\"fr_insline({$row}, true)\" onmouseout=\"fr_insline({$row}, false)\" class=\"btn btn-default btn-xs\"><span class=\"glyphicon glyphicon-arrow-left\"></span></button>";
				break;
			}
			case "edit": {
				echo "<a href=\"{$this->edit_uri}?id={$row}\" class=\"btn btn-default btn-xs\"><span class=\"glyphicon glyphicon-pencil\"></span></a>";
				break;
			}
			case "del": {
				echo "<a href=\"{$this->my_uri}?act=del&amp;id={$row}\" onclick=\"return confirm('".gettext('Do you really want to delete this entry?')."')\" class=\"btn btn-default btn-xs\"><span class=\"glyphicon glyphicon-remove\"></span></a>";
				break;
			}
			case "dup": {
				echo "<a href=\"{$this->edit_uri}?act=dup&amp;id={$row}\" class=\"btn btn-default btn-xs\" title=\"".gettext('Duplicate entry')."\" /><span class=\"glyphicon glyphicon-plus\"></span></a>";
				break;
			}
		}

	}

}

?>
