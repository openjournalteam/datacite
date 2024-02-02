<div class="pkp_content_panel pkp_controllers_grid">
    <div class="pkpListPanel pkpListPanel--submissions">
        <div class="pkpListPanel__body -pkpClearfix pkpListPanel__body--submissions">
            <div class="pkpListPanel__content pkpListPanel__content--submissions">
                <div class="header">
                    <h4>
                        {translate key="plugins.importexport.crossref.monographsOrChapter"}
                    </h4>
                </div>
                <table aria-live="polite" class="pkpListPanel__items">
                    <colgroup>
                        <col class="grid-column column-select" style="width: 5%;">
                        <col class="grid-column column-select" style="width: 15%;">
                        <col class="grid-column column-select" style="width: 80%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col" style="text-align: left;">ID</th>
                            <th scope="col" style="text-align: left;">Author</th>
                            <th scope="col" style="text-align: left;">Content</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $itemsQueue as $key=>$item}
                            <tr class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--hasSummary">
                                <td>
                                    <div class="pkpListPanelItem--submission__id">
                                        {$item["id"]}
                                    </div>
                                </td>
                                <td>
                                    <div class="pkpListPanelItem--submission__author">
                                        {$item["authors"]}
                                    </div>
                                </td>
                                <td>
                                    <div class="pkpListPanelItem__summary -pkpClearfix">
                                        <div class="pkpListPanelItem--submission__item">
                                            <div class="pkpListPanelItem--submission__reviewerWorkflowLink"><span
                                                    class="-screenReader">ID</span>
                                            </div>
                                            <div class="pkpListPanelItem--submission__title">
                                                {$item["title"]}<br />
                                                DOI: {$item["pubId"]}<br />
                                                {if $item["chapterPubIds"]}
                                                    {translate key="plugins.importexport.crossref.chapterDoiCount"}:
                                                    {$item["chapterPubIds"]|count}
                                                {/if}
                                            </div>
                                            <div class="pkpListPanelItem--submission__activity">
                                                {if $item["notices"]}
                                                    {foreach from=$item["notices"] item=$notice}
                                                        <span aria-hidden="true"
                                                            class="fa fa-exclamation-triangle pkpIcon--inline"></span> {$notice}
                                                        <br />
                                                    {/foreach}
                                                {/if}
                                                {if $item["errors"]}
                                                    {foreach from=$item["errors"] item=$error}
                                                        <span aria-hidden="true"
                                                            class="fa fa-exclamation-triangle pkpIcon--inline"></span> {$error}
                                                        <br />
                                                    {/foreach}
                                                {/if}
                                            </div>
                                        </div>
                                        <div class="pkpListPanelItem--submission__stage">
                                            <div class="pkpListPanelItem--submission__stageRow">
                                                {if !$item["errors"]}
                                                    <button class="pkpBadge pkpBadge--button pkpBadge--dot">
                                                        <a href="{$plugin}/export?submission={$item["id"]}" class="">
                                                            {translate key="plugins.importexport.crossref.export"}
                                                        </a>
                                                    </button>
                                                    <button class="pkpBadge pkpBadge--button pkpBadge--dot">
                                                        <a href="{$plugin}/deposit?submission={$item["id"]}" class="">
                                                            {translate key="plugins.importexport.crossref.deposit"}
                                                        </a>
                                                    </button>
                                                {/if}
                                                <div aria-hidden="true" class="pkpListPanelItem--submission__flags">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
        <div class="listPanel__footer">
            <nav role="navigation" aria-label="View additional pages" class="pkpPagination">
                <ul>
                    <li>
                        <button 
                            class="pkpButton" 
                            aria-label="Go to Previous page" 
                            {if !$canClickPrevious} disabled {/if}
                            {if $canClickPrevious} 
                                hx-get="{$plugin}/queuetab?page={$previousPage}" 
                                hx-target="#queue-tab-content"
                                hx-disabled-elt="this" 
                            {/if}
                            >
                            Previous page
                        </button>
                    </li>
                    <li style="margin: 10px; font-size:14px;color:#747474;"> {$offset} - {$totalShowedItem} of {$itemsSizeQueue} Submissions</li>
                    <li>
                        <button 
                            class="pkpButton" 
                            style="display: flex; align-items:center;"
                            id="queue-tab-content"
                            {if !$canClickNext} disabled {/if}
                            {if $canClickNext} 
                                hx-get="{$plugin}/queuetab?page={$nextPage}" 
                                hx-target="#queue-tab-content"
                                hx-disabled-elt="this" 
                            {/if}
                            >
                            Next page 
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>