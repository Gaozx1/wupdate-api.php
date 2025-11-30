# WordPress API Extended

## 项目介绍 (Project Introduction)

WordPress API Extended 是一个扩展 WordPress REST API 功能的插件，旨在提供更强大、更灵活的内容管理接口。该插件支持通过 API 进行文章管理、媒体上传，并提供安全的 API 密钥认证机制，方便开发者通过编程方式与 WordPress 站点进行交互。

WordPress API Extended is a plugin that extends the functionality of the WordPress REST API, aiming to provide more powerful and flexible content management interfaces. This plugin supports article management, media uploads via API, and offers a secure API key authentication mechanism, making it easy for developers to interact with WordPress sites programmatically.


## 功能特点 (Features)

- **扩展 API 端点**：提供更多自定义 REST API 端点，支持文章创建、更新、查询及媒体管理
  - Extended API endpoints: Provides additional custom REST API endpoints for post creation, update, query, and media management
- **直观管理面板**：包含仪表盘、内容管理、媒体上传等页面，便于后台操作
  - Intuitive admin panel: Includes dashboard, content management, media upload pages for easy backend operations
- **安全认证**：支持 API 密钥（X-API-Key）和 Bearer 令牌认证
  - Secure authentication: Supports API key (X-API-Key) and Bearer token authentication
- **媒体管理**：通过 API 上传、查询媒体文件，支持预览和管理
  - Media management: Upload, query media files via API with preview and management support
- **自动更新**：内置插件自动更新功能，确保功能及时迭代
  - Automatic updates: Built-in automatic plugin update function to ensure timely feature iterations
- **完整示例**：提供详细的 API 使用示例代码，降低集成难度
  - Complete examples: Provides detailed API usage example code to reduce integration difficulty
- **多语言支持**：支持国际化，已优化汉化文本
  - Multilingual support: Supports internationalization with optimized Chinese localization


## 安装步骤 (Installation)

1. 下载插件压缩包并解压
   - Download the plugin zip package and unzip it
2. 将 `wupdate-api` 文件夹上传至 WordPress 插件目录 `wp-content/plugins/`
   - Upload the `wupdate-api` folder to the WordPress plugin directory `wp-content/plugins/`
3. 登录 WordPress 后台，在「插件」页面激活该插件
   - Log in to the WordPress admin, activate the plugin on the "Plugins" page
4. 激活后，通过左侧菜单「API Extended」访问插件功能
   - After activation, access the plugin features via the left menu "API Extended"


## 使用指南 (Usage Guide)

1. **生成 API 密钥**：
   - 进入「API Settings」页面，点击「Generate API Key」生成密钥
   - Go to the "API Settings" page, click "Generate API Key" to create a key

2. **基本 API 调用**：
   - 所有请求需在头部包含 API 密钥：`X-API-Key: your_api_key_here`
   - All requests must include the API key in the header: `X-API-Key: your_api_key_here`

3. **创建文章示例**：
   ```javascript
   fetch('https://your-site.com/wp-json/wp-api-extended/v1/posts', {
       method: 'POST',
       headers: {
           'X-API-Key': 'your_api_key',
           'Content-Type': 'application/json'
       },
       body: JSON.stringify({
           title: 'My New Post',
           content: 'Post content here',
           status: 'publish'
       })
   })
   ```

4. **上传媒体示例**：
   ```javascript
   const formData = new FormData();
   formData.append('file', fileInput.files[0]);

   fetch('https://your-site.com/wp-json/wp-api-extended/v1/media', {
       method: 'POST',
       headers: {
           'X-API-Key': 'your_api_key'
       },
       body: formData
   })
   ```


## 主要 API 端点 (Main API Endpoints)

| 端点 (Endpoint)                | 方法 (Method) | 描述 (Description)               |
|-------------------------------|--------------|----------------------------------|
| `/wp-api-extended/v1/posts`    | GET          | 获取文章列表 (Get post list)     |
| `/wp-api-extended/v1/posts`    | POST         | 创建新文章 (Create new post)     |
| `/wp-api-extended/v1/posts/{id}` | PUT         | 更新指定文章 (Update specific post) |
| `/wp-api-extended/v1/media`    | GET          | 获取媒体列表 (Get media list)    |
| `/wp-api-extended/v1/media`    | POST         | 上传新媒体 (Upload new media)    |


## 更新日志 (Changelog)

### 版本 1.1.0 (Version 1.1.0)
- 修复 API 撤销后数据库未删除的 bug
  - Fixed bug where database entries weren't deleted after API revocation
- 添加自动更新功能
  - Added automatic update functionality
- 优化汉化文本
  - Optimized Chinese localization text
- 改进错误处理机制
  - Improved error handling mechanism


## 许可证 (License)

本插件基于 GPL v2 或更高版本许可证开源。
This plugin is open-source under the GPL v2 or later license.


## 作者信息 (Author Information)

- 作者 (Author): gaozx
- 插件主页 (Plugin Homepage): [https://wpapi.uuk.pp.ua/](https://wpapi.uuk.pp.ua/)
- GitHub 仓库 (GitHub Repository): [https://github.com/Gaozx1/wupdate-api/releases](https://github.com/Gaozx1/wupdate-api/releases)
