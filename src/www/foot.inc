
                <footer class="page-foot col-sm-push-2">
				<div class="container-fluid">
					<a target="_blank" href="<?=$g['product_website']?>" class="redlnk"><?=$g['product_name']?></a> (c)
	 <?=$g['product_copyright_years']?> <a href="<?=$g['product_copyright_url']?>" class="tblnk"><?=$g['product_copyright_owner']?></a>
	(<a href="/license.php" class="tblnk"><?= gettext('view license'); ?></a>)
				</div>
			</footer>

		</div>
		<!-- /row-->

		</main>

		<?php if (isset($widgetfiles)):
			$widgetfiles_add = $widgetfiles;
			sort($widgetfiles_add);

		?>
		<div class="modal fade" id="modal_widgets" tabindex="-1" role="dialog" aria-labelledby="modal_widgets_label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                        <h4 class="modal-title" id="modal_widgets_label"><?=gettext("Available Widgets"); ?></h4>
                    </div>

                    <div class="modal-body">
                        <table class="table table-striped table-hover">
	                        <?php
					foreach($widgetfiles_add as $widget):
									if(!stristr($widget, "widget.php"))
										continue;

									$periodpos = strpos($widget, ".");
									$widgetname = substr($widget, 0, $periodpos);
									$nicename = $widgetname;
									$nicename = str_replace("_", " ", $nicename);
									//make the title look nice
									$nicename = ucwords($nicename);

									$widgettitle = $widgetname . "_title";
									$widgettitlelink = $widgetname . "_title_link";
										if (isset($$widgettitle)):
							?>


								<tr><td style="cursor: pointer;" onclick='return addWidget("<?php echo $widgetname; ?>")'><?php echo $$widgettitle; ?></td></tr>
							<?php elseif ($nicename != ""): ?>
								<tr><td style="cursor: pointer;" onclick='return addWidget("<?php echo $widgetname; ?>")'><?php echo $nicename; ?></td></tr>
							<?php endif; endforeach; ?>

                        </table>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div><!-- /modal-content -->
            </div><!-- /modal-dialog -->
        </div><!-- /modal -->
		<?php endif; ?>

	<!-- bootstrap script -->
	<script type="text/javascript" src="/ui/js/bootstrap.min.js"></script>
	<!-- Fancy select with search options -->
	<script type="text/javascript" src="/ui/js/bootstrap-select.min.js"></script>
	<!-- bootstrap dialog -->
	<script src="/ui/js/bootstrap-dialog.js"></script>
	</body>
</html>
