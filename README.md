# Azure Communication Service Sendmail

通过 Azure Communication Service 发送邮件。

## 安装

要求 Blessing Skin Server 的 Laravel 版本不低于 9。 

下载插件源码后，请通过 Blessing Skin 管理面板的「插件管理」页面的「上传压缩包」功能安装插件，或将插件源码解压后放入 Blessing Skin Server 根目录下的 plugins 目录内。

在启用插件前，请先在插件目录下运行 `composer install` 安装插件自身的依赖。

## 配置

在皮肤站的 `.env` 配置文件中添加并填写以下条目：

```bash
ACS_ENDPOINT={Endpoint} # 你的 Azure Communication Service 的 Endpoint 中的域名部分
ACS_ACCESS_KEY={AccessKey} # 你的 Azure Communication Service 的 AccessKey
ACS_DISABLE_TRACKING=true # 是否禁用邮件跟踪，默认为 true
ACS_VERBOSE_LOG=false # 是否记录发件日志，默认 false
```

设置 Mail Driver 和 MailFrom：

```bash
MAIL_MAILER=azure # 此项必须为 azure，否则插件不生效
MAIL_FROM_ADDRESS={Mailfrom} # 你的发件人地址（MailFrom），必须是在 Azure Email Communication Service 中添加过的 MailFrom 地址
```

## 发件日志

如果将 `ACS_VERBOSE_LOG` 设置为 `true`，则会在 Blesing Skin Server 根目录下的 storage/logs/sendmail-azure-cs.log 中记录发件日志。

发件日志除记录邮件的收件人地址外，对于成功请求发送的邮件，还会记录 Azure Communication Service 返回的 `id`；对于请求发送失败的邮件，还会记录 Azure Communication Service 返回的 HTTP 响应状态码及响应体全文。

## 注意事项

本插件目前只能发送简单的 HTML 邮件，不支持发送带内联图片和附件的邮件，也不支持添加抄送（Cc）、密送（Bcc）和回复收件人（Reply-To）。

成功请求发送邮件（Blessing Skin Server 提示「邮件发送成功」）仅代表 Azure Communication Service 接受了发送邮件的请求，不代表邮件发送成功。如需查询邮件发送结果，请打开 `ACS_VERBOSE_LOG`，在发件日志中查询对应邮件的 id 后，自行请求 [相关 API](https://learn.microsoft.com/en-us/rest/api/communication/email/get-send-result) 查询，或在 Azure Communication Service 的 Diagnostic settings 中配置日志转储。

Azure Communication Service 处理邮件发送需要一定的时间，根据我们的观察，从成功请求邮件发送至邮件到达收件人邮箱之间存在大约一到两分钟的延迟。

## 开源许可

MIT
