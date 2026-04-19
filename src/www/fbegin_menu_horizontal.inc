<nav id="horizontal-menu">
    <button class="hmenu-toggle" aria-label="<?= html_safe(gettext('Toggle navigation')) ?>">
        <i class="fa fa-bars"></i>
    </button>
    <ul class="hmenu-nav" id="mainmenu">
<?php foreach ($menuSystem as $topMenuItem): ?>
<?php   if (count($topMenuItem->Children) >= 1): ?>
        <li class="hmenu-item hmenu-has-children<?= $topMenuItem->Selected ? ' active' : '' ?>">
            <a href="#">
                <span class="<?= $topMenuItem->CssClass ?> __iconspacer"></span><?= html_safe($topMenuItem->VisibleName) ?>
            </a>
            <ul class="hmenu-dropdown">
<?php       foreach ($topMenuItem->Children as $subMenuItem): ?>
<?php           if ($subMenuItem->Url == ''): ?>
                <li class="hmenu-item hmenu-has-children<?= $subMenuItem->Selected ? ' active' : '' ?>">
                    <a href="#">
                        <?= html_safe($subMenuItem->VisibleName) ?>
                        <span class="<?= $subMenuItem->CssClass ?>"></span>
                    </a>
                    <ul class="hmenu-flyout">
<?php                   foreach ($subMenuItem->Children as $subsubMenuItem): ?>
<?php                       if ($subsubMenuItem->IsExternal == "Y"): ?>
                        <li class="hmenu-item<?= $subsubMenuItem->Selected ? ' active' : '' ?>">
                            <a href="<?= $subsubMenuItem->Url ?>" target="_blank" rel="noopener noreferrer"><?= html_safe($subsubMenuItem->VisibleName) ?> <i class="fa fa-external-link"></i></a>
                        </li>
<?php                       elseif ($aclObj->isPageAccessible($_SESSION['Username'], $subsubMenuItem->Url)): ?>
                        <li class="hmenu-item<?= $subsubMenuItem->Selected ? ' active' : '' ?>">
                            <a href="<?= $subsubMenuItem->Url ?>"><?= html_safe($subsubMenuItem->VisibleName) ?></a>
                        </li>
<?php                       endif ?>
<?php                   endforeach ?>
                    </ul>
                </li>
<?php           elseif ($subMenuItem->IsExternal == "Y"): ?>
                <li class="hmenu-item<?= $subMenuItem->Selected ? ' active' : '' ?>">
                    <a href="<?= $subMenuItem->Url ?>" target="_blank" rel="noopener noreferrer">
                        <?= html_safe($subMenuItem->VisibleName) ?> <i class="fa fa-external-link"></i>
                    </a>
                </li>
<?php           elseif ($aclObj->isPageAccessible($_SESSION['Username'], $subMenuItem->Url)): ?>
                <li class="hmenu-item<?= $subMenuItem->Selected ? ' active' : '' ?>">
                    <a href="<?= $subMenuItem->Url ?>">
                        <?= html_safe($subMenuItem->VisibleName) ?>
                    </a>
                </li>
<?php           endif ?>
<?php       endforeach ?>
            </ul>
        </li>
<?php   else: ?>
<?php       if ($topMenuItem->IsExternal == "Y"): ?>
        <li class="hmenu-item<?= $topMenuItem->Selected ? ' active' : '' ?>">
            <a href="<?= $topMenuItem->Url ?>" target="_blank" rel="noopener noreferrer">
                <span class="<?= $topMenuItem->CssClass ?> __iconspacer"></span><?= html_safe($topMenuItem->VisibleName) ?> <i class="fa fa-external-link"></i>
            </a>
        </li>
<?php       elseif ($aclObj->isPageAccessible($_SESSION['Username'], $topMenuItem->Url)): ?>
        <li class="hmenu-item<?= $topMenuItem->Selected ? ' active' : '' ?>">
            <a href="<?= $topMenuItem->Url ?>">
                <span class="<?= $topMenuItem->CssClass ?> __iconspacer"></span><?= html_safe($topMenuItem->VisibleName) ?>
            </a>
        </li>
<?php       endif ?>
<?php   endif ?>
<?php endforeach ?>
    </ul>
</nav>
