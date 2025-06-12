export const BASE_URL = "https://event.udn.com/bd_500bowls_vote2025/";
export const pgwWrap = (path, isMobile = false) => {
  // 移除相對路徑前綴，如 './' 或 '../'
  path = path.replace(/^\.\//, "");
  path = path.replace(/^\.\.\/+/g, "");

  // 將 .png 格式轉換為 .webp
  path = path.replace(/\.png$/i, ".webp");
  path = path.replace(/M\.png$/i, "M.webp");
  path = path.replace(/m\.png$/i, "m.webp");

  // 保留 .svg 格式
  return path.endsWith(".svg") ? `${BASE_URL}${path}` : path;
};
