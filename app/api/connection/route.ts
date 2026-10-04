export async function GET() {
  const bridge=!!(process.env.EDGENEXT_PHP_BRIDGE_URL && process.env.EDGENEXT_PHP_BRIDGE_TOKEN);
  const configured=bridge||!!(process.env.SDK_API_PRE&&process.env.SDK_APP_ID&&process.env.SDK_APP_SECRET);
  return Response.json({configured,mode:bridge?'php-sdk':'compatible',baseUrl:process.env.SDK_API_PRE||null},{headers:{'Cache-Control':'no-store'}});
}
