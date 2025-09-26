import React from 'react';
import { createStackNavigator } from '@react-navigation/stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import SplashScreen from '@/screens/SplashScreen';
import LoginScreen from '@/screens/auth/LoginScreen';
import RegisterScreen from '@/screens/auth/RegisterScreen';
import HomeTabs from './HomeTabs';

const Stack = createStackNavigator();
const Tab = createBottomTabNavigator();

export const HomeTabs = () => (
  <Tab.Navigator
    screenOptions={({ route }) => ({
      tabBarIcon: ({ focused, color, size }) => {
        let iconName;
        
        if (route.name === 'Wallet') {
          iconName = focused ? 'wallet' : 'wallet-outline';
        } else if (route.name === 'SOL Groups') {
          iconName = focused ? 'people' : 'people-outline';
        } else if (route.name === 'Ti Kanè') {
          iconName = focused ? 'cash' : 'cash-outline';
        }
        
        return <Ionicons name={iconName} size={size} color={color} />;
      },
      tabBarActiveTintColor: '#3B82F6',
      tabBarInactiveTintColor: 'gray',
      headerStyle: {
        backgroundColor: '#FFFFFF',
      },
      headerTintColor: '#1F2937',
    })}
  >
    <Tab.Screen name="Wallet" component={WalletScreen} />
    <Tab.Screen name="SOL Groups" component={SOLGroupsScreen} />
    <Tab.Screen name="Ti Kanè" component={TiKaneScreen} />
  </Tab.Navigator>
);

export default function AppNavigator() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="Splash" component={SplashScreen} />
      <Stack.Screen name="Login" component={LoginScreen} />
      <Stack.Screen name="Register" component={RegisterScreen} />
      <Stack.Screen name="Home" component={HomeTabs} />
    </Stack.Navigator>
  );
}