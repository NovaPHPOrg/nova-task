<title id="title">后台任务 - {$title}</title>
<style id="style">
    .table-card {
        box-sizing: border-box;
    }
    .task-status {
        font-size: 0.875rem;
        color: rgb(var(--mdui-color-on-surface-variant));
        margin-bottom: 1rem;
    }


    #taskLogBody {
        max-height: 60vh;
        overflow-y: auto;
    }

    .task-log {
        margin: 0;
        padding: 12px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.8125rem;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-all;
        background: rgb(var(--mdui-color-surface-container));
        border-radius: 8px;
    }

    .task-log-line.error {
        color: rgb(var(--mdui-color-error));
    }

    .task-log-line.warn {
        color: #b8860b;
    }

    .task-log-time {
        color: rgb(var(--mdui-color-on-surface-variant));
        margin-right: 8px;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="manage_history" class="mr-2"></mdui-icon>
            <span>后台任务</span>
        </div>

        <div class="col-xs-12">
            <div id="dataTable" class="table-card mt-2" style="width: 100%;min-height: 10rem"></div>
        </div>
    </div>
</div>

<script id="script" src="/task/static/js/list.js?v={$__v}"></script>
