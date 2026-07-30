package com.mysdesign.formadonus

import android.os.Bundle
import android.speech.tts.TextToSpeech
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.delay
import java.util.Locale

private val Blue = Color(0xFF1457D9)
private val Bg = Color(0xFFF6F8FC)

data class Exercise(val name:String,val category:String,val seconds:Int,val cue:String)

val exercises = listOf(
    Exercise("Yerinde Yürüyüş","Isınma",45,"Dik dur ve hafif tempoda yürü"),
    Exercise("Sandalyeye Squat","Bacak",40,"Kalçanı geriye ver, dizlerin içe kaçmasın"),
    Exercise("Eğik Şınav","Üst Vücut",35,"Vücudunu düz tut ve kontrollü in"),
    Exercise("Sırt Çantasıyla Row","Sırt",40,"Sırtını düz tut, dirseklerini geriye çek"),
    Exercise("Romanian Deadlift","Kalça",40,"Kalçanı geriye ver ve sırtını düz tut"),
    Exercise("Glute Bridge","Kalça",35,"Kalçanı yukarı kaldır ve sık"),
    Exercise("Plank","Core",30,"Karnını sık ve nefesini tutma"),
    Exercise("Split Squat","Bacak",35,"Dengeni koru ve kontrollü alçal"),
    Exercise("Omuz Press","Omuz",35,"Çantayı kontrollü şekilde yukarı it"),
    Exercise("Superman","Sırt",30,"Kolları ve gövdeyi hafifçe kaldır"),
    Exercise("Side Plank","Core",25,"Vücudunu düz çizgide tut"),
    Exercise("Tempolu Yürüyüş","Kondisyon",60,"Rahat ama canlı tempoda yürü"),
    Exercise("Yavaş Kegel","Pelvik Taban",30,"Nazikçe sık, tut ve tamamen gevşe"),
    Exercise("Hızlı Kegel","Pelvik Taban",20,"Hızlı sık ve tamamen bırak"),
    Exercise("Reverse Kegel","Gevşeme",30,"Pelvik tabanı gevşet ve aşağı bırak"),
    Exercise("Diyafram Nefesi","Nefes",60,"Karnını doldurarak nefes al, yavaşça ver")
)

class MainActivity : ComponentActivity(), TextToSpeech.OnInitListener {
    private lateinit var tts: TextToSpeech
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        tts = TextToSpeech(this,this)
        setContent { FormaDonusApp { speak(it) } }
    }
    override fun onInit(status:Int){ if(status==TextToSpeech.SUCCESS) tts.language=Locale("tr","TR") }
    private fun speak(text:String){ tts.speak(text,TextToSpeech.QUEUE_FLUSH,null,"coach") }
    override fun onDestroy(){ tts.stop(); tts.shutdown(); super.onDestroy() }
}

@Composable fun FormaDonusApp(speak:(String)->Unit){
    var tab by remember { mutableIntStateOf(0) }
    var workout by remember { mutableStateOf<List<Exercise>?>(null) }
    var completed by remember { mutableIntStateOf(0) }
    MaterialTheme(colorScheme=lightColorScheme(primary=Blue,background=Bg,surface=Color.White)){
        if(workout!=null){ WorkoutScreen(workout!!,speak,{workout=null},{completed++;workout=null}) }
        else Scaffold(
            bottomBar={ NavigationBar { listOf("Ana Sayfa","Program","Egzersizler","Rapor").forEachIndexed{i,t-> NavigationBarItem(selected=tab==i,onClick={tab=i},icon={Icon(listOf(Icons.Default.Home,Icons.Default.CalendarMonth,Icons.Default.FitnessCenter,Icons.Default.BarChart)[i],t)},label={Text(t)}) } } }
        ){p-> Box(Modifier.padding(p).fillMaxSize().background(Bg)){ when(tab){0->Home({workout=personalPlan()},completed);1->ProgramScreen{workout=it};2->LibraryScreen();else->ReportScreen(completed)} } }
    }
}

fun personalPlan()=exercises.filter{it.name in listOf("Yerinde Yürüyüş","Sandalyeye Squat","Eğik Şınav","Sırt Çantasıyla Row","Romanian Deadlift","Glute Bridge","Plank","Diyafram Nefesi")}

@Composable fun Header(title:String,subtitle:String=""){ Column(Modifier.fillMaxWidth().padding(20.dp)){Text(title,fontSize=28.sp,fontWeight=FontWeight.Bold,color=Color(0xFF182033)); if(subtitle.isNotBlank()) Text(subtitle,color=Color.Gray)} }
@Composable fun CardBox(content:@Composable ColumnScope.()->Unit){ Card(Modifier.fillMaxWidth().padding(horizontal=16.dp,vertical=6.dp),shape=RoundedCornerShape(18.dp)){Column(Modifier.padding(18.dp),content=content)} }

@Composable fun Home(start:()->Unit,completed:Int){ LazyColumn{ item{Header("Forma Dönüş","MYS Design • Evde kişisel güçlenme")}; item{CardBox{Text("Bana Özel Evde Güçlenme",fontSize=21.sp,fontWeight=FontWeight.Bold);Spacer(Modifier.height(8.dp));Text("Perşembe ana güç, Cuma destek ve pelvik kontrol programı.");Spacer(Modifier.height(14.dp));Button(start,Modifier.fillMaxWidth()){Icon(Icons.Default.PlayArrow,null);Text("  Antrenmana Başla")}}}; item{CardBox{Text("Bu haftaki ilerleme",fontWeight=FontWeight.Bold);Text("$completed antrenman tamamlandı",fontSize=24.sp,color=Blue)}}; item{CardBox{Text("Güvenlik",fontWeight=FontWeight.Bold);Text("Göğüs ağrısı, ciddi nefes darlığı, baş dönmesi veya keskin ağrı varsa antrenmanı durdur.")}} } }

@Composable fun ProgramScreen(start:(List<Exercise>)->Unit){ var thu by remember{ mutableStateOf(true)};var fri by remember{ mutableStateOf(true)};var pelvic by remember{ mutableStateOf(true)};LazyColumn{item{Header("Akıllı Program","Günleri ve hedefi seç")};item{CardBox{Text("Antrenman günleri",fontWeight=FontWeight.Bold);Row(verticalAlignment=Alignment.CenterVertically){Checkbox(thu,{thu=it});Text("Perşembe");Spacer(Modifier.width(20.dp));Checkbox(fri,{fri=it});Text("Cuma")};Row(verticalAlignment=Alignment.CenterVertically){Switch(pelvic,{pelvic=it});Text("  Seks kontrolü ve pelvik taban desteği")};Button(onClick={val p=personalPlan().toMutableList();if(pelvic)p+=exercises.filter{it.category in listOf("Pelvik Taban","Gevşeme")};start(p.distinct())},modifier=Modifier.fillMaxWidth(),enabled=thu||fri){Text("Programı Oluştur ve Başlat")}}};item{CardBox{Text("Program mantığı",fontWeight=FontWeight.Bold);Text("Isınma → ana güç → yardımcı hareket → core → kondisyon → pelvik taban → nefes sırasıyla otomatik yerleştirilir.")}}} }

@Composable fun LibraryScreen(){ var selected by remember{ mutableStateOf<Exercise?>(null)}; if(selected!=null){AlertDialog(onDismissRequest={selected=null},confirmButton={TextButton({selected=null}){Text("Kapat")}},title={Text(selected!!.name)},text={Text("Kategori: ${selected!!.category}\n\n${selected!!.cue}\n\nÖnerilen süre: ${selected!!.seconds} saniye")})};LazyColumn{item{Header("Egzersizler","Amaca ve kas grubuna göre hareketler")};items(exercises){e->CardBox{Row(Modifier.fillMaxWidth().clickable{selected=e},verticalAlignment=Alignment.CenterVertically){Icon(Icons.Default.DirectionsRun,null,tint=Blue);Spacer(Modifier.width(14.dp));Column(Modifier.weight(1f)){Text(e.name,fontWeight=FontWeight.Bold);Text(e.category,color=Color.Gray)};Icon(Icons.Default.ChevronRight,null)}}}} }

@Composable fun ReportScreen(completed:Int){LazyColumn{item{Header("Raporlar","Gelişimini cihazında takip et")};item{CardBox{Text("Haftalık özet",fontWeight=FontWeight.Bold);Text("Tamamlanan antrenman",color=Color.Gray);Text("$completed",fontSize=42.sp,fontWeight=FontWeight.Bold,color=Blue)}};item{CardBox{Text("Takip edilecek gelişimler",fontWeight=FontWeight.Bold);Text("• Kilo ve vücut ölçümleri\n• Squat ve şınav tekrarları\n• Plank süresi\n• Haftalık devamlılık\n• Pelvik taban ve nefes çalışması")}}} }

@Composable fun WorkoutScreen(plan:List<Exercise>,speak:(String)->Unit,close:()->Unit,finish:()->Unit){
    var index by remember{ mutableIntStateOf(0)};var left by remember{ mutableIntStateOf(plan[0].seconds)};var running by remember{ mutableStateOf(false)};val e=plan[index]
    LaunchedEffect(index){left=e.seconds;running=false;speak("Sıradaki hareket ${e.name}. ${e.cue}")}
    LaunchedEffect(running,left){if(running&&left>0){delay(1000);left--}else if(running&&left==0){running=false;speak("Hareket tamamlandı")}}
    Column(Modifier.fillMaxSize().background(Bg).padding(20.dp),horizontalAlignment=Alignment.CenterHorizontally){
        Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween){IconButton(close){Icon(Icons.Default.Close,null)};Text("${index+1} / ${plan.size}",fontWeight=FontWeight.Bold);Spacer(Modifier.width(48.dp))}
        Spacer(Modifier.height(24.dp));Card(shape=RoundedCornerShape(24.dp),modifier=Modifier.fillMaxWidth()){Column(Modifier.padding(30.dp),horizontalAlignment=Alignment.CenterHorizontally){Icon(Icons.Default.AccessibilityNew,null,tint=Blue,modifier=Modifier.size(130.dp));Text(e.name,fontSize=27.sp,fontWeight=FontWeight.Bold);Text(e.cue,color=Color.Gray);Spacer(Modifier.height(20.dp));Text("$left",fontSize=64.sp,fontWeight=FontWeight.Bold,color=Blue)}}
        Spacer(Modifier.height(20.dp));Button({running=!running},Modifier.fillMaxWidth()){Icon(if(running)Icons.Default.Pause else Icons.Default.PlayArrow,null);Text(if(running)" Duraklat" else " Başlat")}
        Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween){TextButton(enabled=index>0,onClick={index--}){Text("Önceki")};TextButton(onClick={if(index<plan.lastIndex){index++}else finish()}){Text(if(index<plan.lastIndex)"Sonraki" else "Antrenmanı Bitir")}}
    }
}
