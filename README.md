# 📚 2025 聯合故事獎 投票平台

## 📝 專案簡介

本專案為 2025 聯合故事獎投票系統，提供使用者投票評選優秀圖書作品的平台。

- 前台網址：https://event.udn.com/bd_2024storyawards/
- 後台網址：https://lab-event.udn.com/bd_2025storyawards/admin/

## 🛠️ 技術架構

### 前端

- **框架**：Astro.js
- **樣式**：SCSS/CSS
- **互動**：JavaScript
- **UI 組件**：SweetAlert2

### 後端

- **環境**：PHP
- **資料庫**：Redis (使用 Predis 客戶端)
- **安全性**：Cloudflare Turnstile (防機器人驗證)
- **認證**：會員系統整合

## 🔧 主要功能

1. **書籍投票系統**

   - 使用者每日投票限制
   - 即時投票統計
   - 排行榜顯示

2. **會員功能**

   - 會員登入/驗證
   - 投票紀錄查詢
   - 折扣碼發放機制

3. **管理後台**
   - 書籍資料管理
   - 投票數據統計
   - 系統設定管理

## 📄 專案結構

```
src/
  ├── API/            # 後端PHP API
  ├── components/     # Astro組件
  ├── layouts/        # 頁面佈局
  ├── pages/          # 頁面路由
  └── styles/         # 樣式文件
public/
  └── image/          # 靜態圖片資源
```

## 🚀 開發與部署

### 本地開發

1. 安裝依賴：`npm install` 或 `pnpm install`
2. 啟動開發伺服器：`npm run dev` 或 `pnpm dev`
3. 訪問：`http://localhost:3000`

### 建置與部署

1. 建置生產版本：`npm run build` 或 `pnpm build`
2. 部署到伺服器：將建置後的檔案上傳至主機

## 👥 聯絡資訊

若有問題或建議，請聯絡專案維護人員。
"# 500_bowl_test" 
