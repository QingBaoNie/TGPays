# TG支付机器人

TG支付机器人是一个基于 PHP、MySQL 和 Telegram Bot API 的支付机器人，支持对接易支付接口，实现微信和支付宝的支付功能，并配有网页管理后台。该项目包含以下功能：

- **配置安装**：通过配置页面设置 MySQL、Redis、Telegram 机器人 Token、主人 ID 和完整域名，自动生成配置文件并导入数据库表。
- **TG机器人核心逻辑**：支持 `/start`、`/pay` 和 `/login` 指令。  
  - `/pay 金额` 生成订单，并提供内联按钮选择支付方式（支付宝或微信）。  
  - 根据用户选择，调用易支付 API 发起支付请求，并根据返回数据生成二维码展示给用户。  
  - `/login` 指令仅允许配置的主人获取一次性后台登录链接。
- **易支付接口对接**：调用易支付接口进行支付请求，支持 MD5 签名算法，返回支付链接/二维码或小程序支付 URL。
- **二维码生成**：根据易支付返回数据自动生成二维码图片，并通过 Telegram 机器人发送给用户。
- **网页管理后台**：使用 [Layui](https://www.layui.com/) 布局的后台管理界面，支持订单查询和后台登录。

## 环境要求

- **操作系统**：Linux
- **Web 服务器**：Nginx 或 Apache
- **PHP**：PHP 7.2 或更高版本（建议 PHP 7.4+）
- **MySQL**：MySQL 5.7 或以上
- **Redis**（可选）：用于缓存/会话管理


## 依赖与第三方服务

- [Telegram Bot API](https://core.telegram.org/bots/api)
- [易支付接口](#)（请参考易支付 API 文档）
- [Layui](https://www.layui.com/)（后台管理页面样式库）
- 免费二维码生成 API：`https://api.qrserver.com/v1/create-qr-code/`


## 安装与部署

1. **环境准备**  
   - 部署 PHP、MySQL、Redis 等环境
   - 配置 Web 服务器，确保 PHP 与数据库能正常运行。

2. **上传代码**  
   - 将本项目所有文件上传到您的服务器指定目录。

3. **配置安装**  
   - 访问 `config_install.php` 页面，填写 MySQL、Redis、TG Token、主人ID 及完整域名等配置，提交后系统将生成 `config.php` 文件。
  

4. **配置易支付接口**  
   - 访问 `admin/easypay_config.php`，填写易支付接口参数（包括商户ID、密钥、网关地址、通知地址等），保存配置。

5. **启动 TG 机器人**  
   - 在aapanel应用商店安装supervisord Run User为www 执行命令为 `/www/server/php/74/bin/php /www/wwwroot/xxxxx/bot.php`。



## 使用说明

- **用户交互**  
  - 发送 `/start` 查看帮助信息。  
  - 发送 `/pay <金额>` 生成订单，并选择支付方式。  
  - 选择支付宝或微信后，机器人将生成二维码发送给您。  
  - 发送 `/login`（仅限主人ID）可获得后台登录链接。

- **后台管理**  
  - 访问 `admin/login.php` 使用一次性登录链接进入后台。  
  - 后台可查看支付记录、订单统计及易支付配置。

## 注意事项

- 确保易支付后台中配置的商户ID、密钥与您在 `admin/easypay_config.php` 中填写的完全一致。  
- Telegram 机器人需在私聊中使用，群组使用时请确保机器人具有相应的管理权限（如删除消息等）。  
- 二维码生成依赖第三方免费 API，若有大流量建议自行部署二维码生成服务。

## 开源许可

本项目采用 MIT 许可证，详见 [LICENSE](LICENSE)。

---

欢迎提出 issues 和 pull requests，感谢您的支持！
