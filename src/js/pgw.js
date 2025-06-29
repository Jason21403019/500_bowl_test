export const BASE_URL = "https://event.udn.com/bd_500bowls_vote2025";

const CONFIG = {
  usePGW: true,
};
export const pgwWrap = (path) => {
  if (CONFIG.usePGW) {
    return `https://pgw.udn.com.tw/gw/photo.php?u=${BASE_URL}${path}&nt=1&v=20250620`;
  } else {
    return `${BASE_URL}${path}`;
  }
};
