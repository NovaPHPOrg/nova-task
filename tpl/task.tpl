<title id="title">系统任务 - {$title}</title>
<style id="style">


    .table-card {
        box-sizing: border-box;
    }

    mdui-card{
        width: 100%;
    }
</style>

<div id="container" class="container">
    <div class="row  col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="schedule" class="refresh mr-2"></mdui-icon>
            <span>系统任务</span>
        </div>
        <div class="col-xs12">

            <!-- 数据表格卡片 -->
            <div id="dataTable" class="table-card mt-2" style="width: 100%;min-height: 10rem"></div>
        </div>
    </div>

</div>

<script id="script">
    window.pageLoadFiles = [
        'DataTable',
    ];

    window.pageOnLoad = function (loading) {

        const orderTable = new DataTable("#dataTable");
        // 初始化表格
        orderTable.load({
            uri: "/task/list",
            // 表格高度
            height: "auto",
            // 表格行高
            lineHeight: "auto",
            // 移动端适配
            mobile: true,
            // 分页设置
            page: true,

            pageSizes: [10, 20, 50, 100],
            // 请求参数
            selectable: false,
            // 列设置
            columns: [
                {
                    field: "key",
                    name: "任务ID",
                    align: "center",
                    width: 150
                },
                {
                    field: "name",
                    name: "任务名称",
                    align: "center",
                    width: 200
                },
                {
                    field: "cron",
                    name: "Cron表达式",
                    align: "center",
                    width: 150
                },
                {
                    field: "next",
                    name: "下次执行时间",
                    align: "center",
                    width: 180,
                    formatter: function (value) {
                        return value ?
                            new Date(value * 1000).toLocaleString()
                            : '-';
                    }
                },
                {
                    field: "loop",
                    name: "是否循环",
                    align: "center",
                    width: 100,
                    formatter: function (value) {
                        return value ?
                            '是'
                            : '否';
                    }
                },
                {
                    field: "times",
                    name: "执行次数",
                    align: "center",
                }
            ],
        });



        window.pageOnUnLoad = function () {
            // 页面卸载时的清理工作
        };

    };

</script>



