{extends file="layouts/backend.tpl"}
{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle|escape}
	</h1>
    <script type="text/javascript">
        // Attach the JS file tab handler.
        $(function () {ldelim}
            $('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
            $('#importExportTabs').tabs('option', 'cache', true);
            {rdelim});
    </script>
    <div id="importExportTabs" class="pkp_controllers_tab">
        <ul>
            <li><a href="#queue-tab">{translate key="plugins.importexport.crossref.queued"}</a></li>
            <li><a href="#deposited-tab">{translate key="plugins.importexport.crossref.deposited"}</a></li>
            <li><a href="#settings-tab">{translate key="plugins.importexport.crossref.settings"}</a></li>
        </ul>
        <div id="settings-tab">
            <script type="text/javascript">
                $(function () {ldelim}
                    $('#crossrefSettingsForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                    {rdelim});
            </script>
            <form class="pkp_form" id="crossrefSettingsForm" method="post"
                action="{plugin_url path="settings" verb="save"}">
                {if $doiPluginSettingsLinkAction}
                    {fbvFormArea id="doiPluginSettingsLink"}
                    {fbvFormSection}
                        {include file="linkAction/linkAction.tpl" action=$doiPluginSettingsLinkAction}
                    {/fbvFormSection}
                    {/fbvFormArea}
                {/if}
                {fbvFormArea id="crossrefSettingsFormArea"}
                    <p class="pkp_help">{translate key="plugins.importexport.crossref.settings.description"}</p>
                    <p class="pkp_help">{translate key="plugins.importexport.crossref.intro"}</p>
                {fbvFormSection}
                {fbvElement type="text" id="username" value=$username label="plugins.importexport.crossref.settings.form.username" maxlength="50" size=$fbvStyles.size.MEDIUM}
                {fbvElement type="text" password="true" id="password" value=$password label="plugins.importexport.crossref.settings.form.password" maxLength="50" size=$fbvStyles.size.MEDIUM}
                    <span class="instruct">{translate key="plugins.importexport.crossref.settings.form.password.description"}</span>
                    <br/>
                {/fbvFormSection}
                {fbvFormSection list="true"}
                {fbvElement type="checkbox" id="testMode" label="plugins.importexport.crossref.settings.form.testMode.description" checked=$testMode|compare:true}
                {/fbvFormSection}
                {/fbvFormArea}
                {fbvFormButtons submitText="common.save"}
            </form>
        </div>
        <div id="queue-tab">
            <script type="text/javascript">
                $(function () {ldelim}
                    $('#queueXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                    {rdelim});
            </script>
            <div class="listing" width="100%">
                <div 
                    hx-get="{$plugin}/queuetab" 
                    hx-trigger="intersect"
                    hx-swap="innerHTML"
                    id="queue-tab-content"
                    >
                    Loading Table ...
                </div>
            </div>
        </div>
        <div id="deposited-tab">
            <script type="text/javascript">
                $(function () {ldelim}
                    $('#depositedXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                    {rdelim});
            </script>
            <div class="listing" width="100%">
                <div 
                    hx-get="{$plugin}/depositedtab" 
                    hx-trigger="intersect"
                    hx-swap="innerHTML"
                    id="deposited-tab-content"
                    >
                    Loading Table ...
                </div>
            </div>
        </div>
    </div>
    <script src="{$htmxjs}"></script>

{/block}